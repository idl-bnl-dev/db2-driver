<?php

use IDL\DB2Driver\Tests\Support\FakeDB2Connection;
use IDL\DB2Driver\Tests\Support\Models\User;
use Illuminate\Database\ConnectionResolver;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;

/**
 * Runs real Eloquent models through the DB2 grammar and processor.
 * Only the database round trip is faked, so these tests assert both the SQL the
 * driver emits and how Eloquent turns the returned rows into models.
 */
beforeEach(function () {
    $this->db = db2();
    Model::setConnectionResolver(new ConnectionResolver(['db2' => $this->db]));
});

afterEach(function () {
    Model::unsetConnectionResolver();
});

function rows(int ...$ids): array
{
    return array_map(fn ($id) => (object) ['id' => $id, 'name' => "user {$id}"], $ids);
}

function lastSelect(FakeDB2Connection $db): string
{
    return end($db->selects)['query'];
}

describe('find', function () {
    it('finds a model by primary key', function () {
        $this->db->selectResults = [rows(1)];

        $user = User::find(1);

        expect($user)->toBeInstanceOf(User::class)
            ->and($user->name)->toBe('user 1')
            ->and($user->exists)->toBeTrue()
            ->and(lastSelect($this->db))->toBe('select * from users where users.id = ? FETCH FIRST 1 ROWS ONLY')
            ->and($this->db->selects[0]['bindings'])->toBe([1]);
    });

    it('returns null when nothing is found', function () {
        expect(User::find(99))->toBeNull();
    });

    it('throws from findOrFail when nothing is found', function () {
        User::findOrFail(99);
    })->throws(ModelNotFoundException::class);

    it('finds many models by primary key', function () {
        $this->db->selectResults = [rows(1, 2)];

        $users = User::find([1, 2]);

        expect($users->pluck('id')->all())->toBe([1, 2])
            ->and(lastSelect($this->db))->toBe('select * from users where users.id in (1, 2)');
    });
});

describe('get', function () {
    it('hydrates a collection of models', function () {
        $this->db->selectResults = [rows(1, 2, 3)];

        $users = User::get();

        expect($users)->toHaveCount(3)
            ->and($users->first())->toBeInstanceOf(User::class)
            ->and($users->pluck('name')->all())->toBe(['user 1', 'user 2', 'user 3'])
            ->and(lastSelect($this->db))->toBe('select * from users');
    });

    it('applies where, order by and limit', function () {
        $this->db->selectResults = [rows(1)];

        User::where('name', 'like', 'a%')->orderBy('id', 'desc')->limit(5)->get();

        expect(lastSelect($this->db))->toBe('select * from users where name like ? order by id desc FETCH FIRST 5 ROWS ONLY')
            ->and($this->db->selects[0]['bindings'])->toBe(['a%']);
    });

    it('returns an empty collection when there are no rows', function () {
        expect(User::where('id', 0)->get())->toBeEmpty();
    });

    it('gets the first model', function () {
        $this->db->selectResults = [rows(7)];

        expect(User::orderBy('id')->first()->id)->toBe(7)
            ->and(lastSelect($this->db))->toBe('select * from users order by id asc FETCH FIRST 1 ROWS ONLY');
    });

    it('chunks and offsets with row_number()', function () {
        $this->db->selectResults = [rows(11, 12)];

        User::orderBy('id')->offset(10)->limit(2)->get();

        expect(lastSelect($this->db))->toBe(
            'select * from (select users.*,  row_number() over (order by id asc) as row_num from users) as temp_table where row_num between 11 and 12'
        );
    });
});

describe('pagination', function () {
    it('paginates the first page', function () {
        $this->db->selectResults = [[(object) ['aggregate' => 40]], rows(1, 2, 3)];

        $page = User::orderBy('id')->paginate(15, ['*'], 'page', 1);

        expect($page)->toBeInstanceOf(LengthAwarePaginator::class)
            ->and($page->total())->toBe(40)
            ->and($page->lastPage())->toBe(3)
            ->and($page->currentPage())->toBe(1)
            ->and($page->items())->toHaveCount(3)
            ->and($page->items()[0])->toBeInstanceOf(User::class)
            ->and($this->db->selects[0]['query'])->toBe('select count(*) as aggregate from users')
            ->and($this->db->selects[1]['query'])->toBe('select * from users order by id asc FETCH FIRST 15 ROWS ONLY');
    });

    it('paginates later pages through the row_number() offset', function () {
        $this->db->selectResults = [[(object) ['aggregate' => 40]], rows(16, 17)];

        $page = User::orderBy('id')->paginate(15, ['*'], 'page', 2);

        expect($page->currentPage())->toBe(2)
            ->and($page->items())->toHaveCount(2)
            ->and($this->db->selects[1]['query'])->toBe(
                'select * from (select users.*,  row_number() over (order by id asc) as row_num from users) as temp_table where row_num between 16 and 30'
            );
    });

    it('skips the page query when the table is empty', function () {
        $this->db->selectResults = [[(object) ['aggregate' => 0]]];

        $page = User::paginate(15);

        expect($page->total())->toBe(0)
            ->and($page->items())->toBeEmpty()
            ->and($this->db->selects)->toHaveCount(1);
    });

    it('simple paginates by fetching one extra row', function () {
        $this->db->selectResults = [rows(16, 17, 18)];

        $page = User::orderBy('id')->simplePaginate(2, ['*'], 'page', 2);

        expect($page)->toBeInstanceOf(Paginator::class)
            ->and($page->hasMorePages())->toBeTrue()
            ->and($page->items())->toHaveCount(2)
            ->and(lastSelect($this->db))->toBe(
                'select * from (select users.*,  row_number() over (order by id asc) as row_num from users) as temp_table where row_num between 3 and 5'
            );
    });

    it('uses limit/offset when offset compatibility mode is off', function () {
        $db = db2(['offset_compatibility_mode' => false]);
        Model::setConnectionResolver(new ConnectionResolver(['db2' => $db]));
        $db->selectResults = [[(object) ['aggregate' => 40]], rows(16)];

        User::orderBy('id')->paginate(15, ['*'], 'page', 2);

        expect($db->selects[1]['query'])->toBe('select * from users order by id asc limit 15 offset 15');
    });
});

describe('create', function () {
    it('inserts through "select from new table" and sets the generated id', function () {
        $this->db->selectResults = [[(object) ['ID' => '5']]];

        $user = User::create(['name' => 'Ada']);

        expect($user->id)->toBe(5)
            ->and($user->exists)->toBeTrue()
            ->and(lastSelect($this->db))->toBe('select id from new table (insert into users (name) values (?))')
            ->and($this->db->selects[0]['bindings'])->toBe(['Ada']);
    });
});
