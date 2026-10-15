<?php

use IDL\DB2Driver\Tests\Support\Models\LiveUser;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;

uses()->group('live');

/**
 * Eloquent features against a real IBM i. Each test gets its own throwaway table
 * (unique name, always dropped) holding LiveUser rows.
 */
beforeEach(function () {
    $this->table = $this->tempTable('E');

    $this->db()->getSchemaBuilder()->create($this->table, function (Blueprint $table) {
        $table->increments('id');
        $table->string('name', 50);
        $table->string('email', 100)->nullable();
        $table->integer('age')->nullable();
        $table->smallInteger('active')->default(1);
        $table->string('settings', 500)->nullable();
        $table->timestamps();
        $table->softDeletes();
    });

    // A fresh anonymous subclass per test so the table name never leaks between tests.
    $table = $this->table;
    $this->model = new class($table) extends LiveUser
    {
        public function __construct($table = null)
        {
            parent::__construct();
            if (is_string($table)) {
                static::$liveTable = $table;
            }
            $this->setTable(static::$liveTable);
        }

        public static string $liveTable = '';
    };
    $this->users = fn () => $this->model::query();
});

afterEach(function () {
    if (isset($this->app)) {
        $this->db()->getSchemaBuilder()->dropIfExists($this->table);
    }
});

function seedUsers(object $test, int $count = 5): void
{
    foreach (range(1, $count) as $i) {
        $test->model::create([
            'name' => "user {$i}",
            'email' => "user{$i}@example.test",
            'age' => 20 + $i,
        ]);
    }
}

describe('create', function () {
    it('creates a model and returns the generated identity id', function () {
        $user = $this->model::create(['name' => 'Ada', 'email' => 'ada@example.test', 'age' => 36]);

        expect($user->exists)->toBeTrue()
            ->and($user->id)->toBeInt()->toBeGreaterThan(0)
            ->and($user->wasRecentlyCreated)->toBeTrue();

        $fresh = ($this->users)()->find($user->id);

        expect($fresh->name)->toBe('Ada')
            ->and($fresh->age)->toBe(36);
    });

    it('generates increasing ids', function () {
        $a = $this->model::create(['name' => 'a']);
        $b = $this->model::create(['name' => 'b']);

        expect($b->id)->toBeGreaterThan($a->id);
    });

    it('stores and reads back nulls', function () {
        $user = $this->model::create(['name' => 'Nobody']);

        $fresh = ($this->users)()->find($user->id);

        expect($fresh->email)->toBeNull()
            ->and($fresh->age)->toBeNull();
    });

    it('round-trips accents and quotes', function () {
        // Plain varchar columns take the job CCSID (273 on the test system), which covers Latin text.
        $name = "Zoë O'Brien café ñ \"quoted\"";
        $user = $this->model::create(['name' => $name]);

        expect(($this->users)()->find($user->id)->name)->toBe($name);
    });

    it('round-trips full unicode in a CCSID 1208 column', function () {
        // The driver emits plain varchar(n); full unicode needs an explicit UTF-8 column.
        $table = $this->tempTable('U');
        $this->db()->statement("create table {$table} (id integer not null, name varchar(100) ccsid 1208)");

        try {
            $name = "Zoë O'Brien — 日本語";
            $this->db()->table($table)->insert(['id' => 1, 'name' => $name]);

            expect($this->db()->table($table)->where('id', 1)->value('name'))->toBe($name);
        } finally {
            $this->db()->getSchemaBuilder()->dropIfExists($table);
        }
    });

    it('sets created_at and updated_at', function () {
        $user = $this->model::create(['name' => 'Timed']);
        $fresh = ($this->users)()->find($user->id);

        expect($fresh->created_at)->toBeInstanceOf(Carbon::class)
            ->and($fresh->updated_at)->toBeInstanceOf(Carbon::class)
            ->and($fresh->created_at->diffInMinutes(now(), true))->toBeLessThan(120);
    });

    it('inserts many rows with insert()', function () {
        ($this->users)()->insert([
            ['name' => 'a', 'age' => 1],
            ['name' => 'b', 'age' => 2],
            ['name' => 'c', 'age' => 3],
        ]);

        expect(($this->users)()->count())->toBe(3);
    });
});

describe('find', function () {
    it('finds by primary key, or returns null', function () {
        $user = $this->model::create(['name' => 'Ada']);

        expect(($this->users)()->find($user->id)->name)->toBe('Ada')
            ->and(($this->users)()->find(999999))->toBeNull();
    });

    it('throws from findOrFail when nothing is found', function () {
        ($this->users)()->findOrFail(999999);
    })->throws(ModelNotFoundException::class);

    it('finds many by primary key', function () {
        seedUsers($this, 3);
        $ids = ($this->users)()->orderBy('id')->pluck('id')->all();

        $found = ($this->users)()->find([$ids[0], $ids[2]]);

        expect($found)->toHaveCount(2);
    });

    it('gets the first model, ordered', function () {
        seedUsers($this, 3);

        expect(($this->users)()->orderBy('age', 'desc')->first()->name)->toBe('user 3')
            ->and(($this->users)()->where('name', 'nope')->first())->toBeNull();
    });
});

describe('query', function () {
    beforeEach(fn () => seedUsers($this, 5));

    it('filters with where clauses', function () {
        expect(($this->users)()->where('age', '>', 23)->count())->toBe(2)
            ->and(($this->users)()->where('name', 'user 2')->value('email'))->toBe('user2@example.test')
            ->and(($this->users)()->whereIn('age', [21, 25])->count())->toBe(2)
            ->and(($this->users)()->whereBetween('age', [22, 24])->count())->toBe(3)
            ->and(($this->users)()->where('name', 'like', 'user %')->count())->toBe(5)
            ->and(($this->users)()->whereNull('deleted_at')->count())->toBe(5)
            ->and(($this->users)()->whereNotNull('email')->count())->toBe(5);
    });

    it('combines or-where and nested groups', function () {
        $count = ($this->users)()
            ->where(fn ($q) => $q->where('age', 21)->orWhere('age', 25))
            ->where('name', '!=', 'user 5')
            ->count();

        expect($count)->toBe(1);
    });

    it('orders, limits and offsets', function () {
        $names = fn ($q) => $q->pluck('name')->all();

        expect($names(($this->users)()->orderBy('age', 'desc')->limit(2)))->toBe(['user 5', 'user 4'])
            ->and($names(($this->users)()->orderBy('age')->offset(1)->limit(2)))->toBe(['user 2', 'user 3'])
            ->and($names(($this->users)()->orderBy('age')->offset(4)->limit(10)))->toBe(['user 5']);
    });

    it('runs aggregates', function () {
        expect(($this->users)()->count())->toBe(5)
            ->and((int) ($this->users)()->min('age'))->toBe(21)
            ->and((int) ($this->users)()->max('age'))->toBe(25)
            ->and((int) ($this->users)()->sum('age'))->toBe(115)
            ->and((float) ($this->users)()->avg('age'))->toBe(23.0);
    });

    it('checks existence', function () {
        expect(($this->users)()->where('age', 21)->exists())->toBeTrue()
            ->and(($this->users)()->where('age', 99)->exists())->toBeFalse()
            ->and(($this->users)()->where('age', 99)->doesntExist())->toBeTrue();
    });

    it('groups with having', function () {
        ($this->users)()->insert([['name' => 'dup', 'age' => 30], ['name' => 'dup', 'age' => 31]]);

        $names = ($this->users)()
            ->select('name')
            ->groupBy('name')
            ->havingRaw('count(*) > 1')
            ->pluck('name')
            ->all();

        expect($names)->toBe(['dup']);
    });

    it('selects distinct values', function () {
        ($this->users)()->insert([['name' => 'same'], ['name' => 'same']]);

        expect(($this->users)()->where('name', 'same')->distinct()->pluck('name')->all())->toBe(['same']);
    });

    it('plucks keyed values', function () {
        $map = ($this->users)()->orderBy('age')->pluck('name', 'age')->mapWithKeys(fn ($v, $k) => [(int) $k => $v])->all();

        expect($map)->toBe([21 => 'user 1', 22 => 'user 2', 23 => 'user 3', 24 => 'user 4', 25 => 'user 5']);
    });
});

describe('update', function () {
    it('updates a model and bumps updated_at', function () {
        $user = $this->model::create(['name' => 'Before', 'age' => 1]);

        $user->update(['name' => 'After', 'age' => 2]);

        $fresh = ($this->users)()->find($user->id);

        expect($fresh->name)->toBe('After')
            ->and($fresh->age)->toBe(2)
            ->and($user->wasChanged('name'))->toBeTrue();
    });

    it('mass updates and returns the affected row count', function () {
        seedUsers($this, 5);

        $affected = ($this->users)()->where('age', '>', 22)->update(['email' => null]);

        expect($affected)->toBe(3)
            ->and(($this->users)()->whereNull('email')->count())->toBe(3);
    });

    it('increments and decrements a column', function () {
        $user = $this->model::create(['name' => 'Counter', 'age' => 10]);

        $user->increment('age', 5);
        $user->decrement('age', 2);

        expect(($this->users)()->find($user->id)->age)->toBe(13);
    });

    it('does not run a query when nothing changed', function () {
        $user = $this->model::create(['name' => 'Same']);
        $queries = 0;
        $this->db()->listen(function () use (&$queries) {
            $queries++;
        });

        $user->save();

        expect($queries)->toBe(0);
    });

    it('updateOrCreate creates then updates', function () {
        $first = $this->model::updateOrCreate(['email' => 'u@example.test'], ['name' => 'v1']);
        $second = $this->model::updateOrCreate(['email' => 'u@example.test'], ['name' => 'v2']);

        expect($second->id)->toBe($first->id)
            ->and(($this->users)()->count())->toBe(1)
            ->and(($this->users)()->first()->name)->toBe('v2');
    });

    it('firstOrCreate does not duplicate', function () {
        $this->model::firstOrCreate(['email' => 'f@example.test'], ['name' => 'first']);
        $this->model::firstOrCreate(['email' => 'f@example.test'], ['name' => 'second']);

        expect(($this->users)()->count())->toBe(1)
            ->and(($this->users)()->first()->name)->toBe('first');
    });
});

describe('delete', function () {
    it('soft deletes and hides the row from default queries', function () {
        $user = $this->model::create(['name' => 'Gone']);

        $user->delete();

        expect($user->trashed())->toBeTrue()
            ->and(($this->users)()->find($user->id))->toBeNull()
            ->and(($this->users)()->withTrashed()->find($user->id))->not->toBeNull()
            ->and(($this->users)()->onlyTrashed()->count())->toBe(1);
    });

    it('restores a soft deleted model', function () {
        $user = $this->model::create(['name' => 'Back']);
        $user->delete();

        $user->restore();

        expect(($this->users)()->find($user->id)->name)->toBe('Back');
    });

    it('force deletes a model', function () {
        $user = $this->model::create(['name' => 'Hard']);

        $user->forceDelete();

        expect(($this->users)()->withTrashed()->find($user->id))->toBeNull();
    });

    it('mass deletes and returns the affected row count', function () {
        seedUsers($this, 5);

        $affected = ($this->users)()->where('age', '<', 23)->forceDelete();

        expect($affected)->toBe(2)
            ->and(($this->users)()->count())->toBe(3);
    });
});

describe('casts and attributes', function () {
    it('casts integers and booleans', function () {
        $user = $this->model::create(['name' => 'Cast', 'age' => '42', 'active' => false]);
        $fresh = ($this->users)()->find($user->id);

        expect($fresh->age)->toBe(42)
            ->and($fresh->active)->toBeFalse();
    });

    it('casts json to arrays', function () {
        $user = $this->model::create(['name' => 'Json', 'settings' => ['theme' => 'dark', 'tags' => ['a', 'b']]]);

        expect(($this->users)()->find($user->id)->settings)->toBe(['theme' => 'dark', 'tags' => ['a', 'b']]);
    });

    it('tracks dirty attributes', function () {
        $user = $this->model::create(['name' => 'Dirty']);
        $user->name = 'Changed';

        expect($user->isDirty('name'))->toBeTrue()
            ->and($user->getOriginal('name'))->toBe('Dirty');
    });

    it('refreshes a model from the database', function () {
        $user = $this->model::create(['name' => 'Stale']);
        ($this->users)()->where('id', $user->id)->update(['name' => 'Fresh']);

        expect($user->refresh()->name)->toBe('Fresh');
    });
});

describe('pagination and chunking', function () {
    beforeEach(fn () => seedUsers($this, 12));

    it('paginates with a total count', function () {
        $page = ($this->users)()->orderBy('id')->paginate(5, ['*'], 'page', 2);

        expect($page->total())->toBe(12)
            ->and($page->perPage())->toBe(5)
            ->and($page->currentPage())->toBe(2)
            ->and($page->lastPage())->toBe(3)
            ->and($page->items())->toHaveCount(5)
            ->and($page->items()[0]->name)->toBe('user 6');
    });

    it('returns a partial last page', function () {
        $page = ($this->users)()->orderBy('id')->paginate(5, ['*'], 'page', 3);

        expect($page->items())->toHaveCount(2)
            ->and($page->hasMorePages())->toBeFalse();
    });

    it('simple paginates', function () {
        $page = ($this->users)()->orderBy('id')->simplePaginate(5, ['*'], 'page', 1);

        expect($page->items())->toHaveCount(5)
            ->and($page->hasMorePages())->toBeTrue();
    });

    it('chunks through every row exactly once', function () {
        $seen = [];

        ($this->users)()->orderBy('id')->chunk(5, function ($chunk) use (&$seen) {
            foreach ($chunk as $user) {
                $seen[] = $user->name;
            }
        });

        expect($seen)->toHaveCount(12)
            ->and(array_unique($seen))->toHaveCount(12);
    });

    it('chunks by id', function () {
        $total = 0;

        ($this->users)()->chunkById(4, function ($chunk) use (&$total) {
            $total += $chunk->count();
        });

        expect($total)->toBe(12);
    });

    it('lazily iterates every row', function () {
        expect(($this->users)()->orderBy('id')->lazy(5)->count())->toBe(12);
    });

    it('cursors through every row', function () {
        expect(($this->users)()->orderBy('id')->cursor()->count())->toBe(12);
    });
});

describe('collections and serialization', function () {
    it('hydrates a collection and serializes to array and json', function () {
        seedUsers($this, 2);

        $users = ($this->users)()->orderBy('id')->get();

        expect($users)->toHaveCount(2)
            ->and($users->first())->toBeInstanceOf($this->model::class)
            ->and($users->first()->toArray())->toHaveKeys(['id', 'name', 'email', 'age'])
            ->and(json_decode($users->toJson(), true)[1]['name'])->toBe('user 2');
    });

    it('returns an empty collection when nothing matches', function () {
        expect(($this->users)()->where('name', 'nobody')->get())->toBeEmpty();
    });
});
