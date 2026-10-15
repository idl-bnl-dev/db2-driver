<?php

use IDL\DB2Driver\Tests\Support\LiveSchema;
use IDL\DB2Driver\Tests\Support\Models\Live\LiveAuthor;
use IDL\DB2Driver\Tests\Support\Models\Live\LiveComment;
use IDL\DB2Driver\Tests\Support\Models\Live\LiveCountry;
use IDL\DB2Driver\Tests\Support\Models\Live\LivePost;
use IDL\DB2Driver\Tests\Support\Models\Live\LiveProfile;
use IDL\DB2Driver\Tests\Support\Models\Live\LiveTag;
use Illuminate\Database\Eloquent\Relations\Relation;
use Illuminate\Database\Schema\Blueprint;

uses()->group('live');

/**
 * Eloquent relationships against a real IBM i.
 *
 * The schema (see LiveSchema) is created once per run; every test starts from empty
 * tables and builds the fixture below:
 *
 *   nl  (Netherlands)  -> ann (2 profile, posts: "A1" 100 views, "A2" 50, "A3" 10), bob (profile, post "B1" 5)
 *   be  (Belgium)      -> cy  (no profile, no posts)
 *   tags: php, db2, laravel  - A1: php(w 3) + db2(w 1), A2: php(w 2), B1: db2(w 1) + laravel(w 5)
 *   comments: 2 on A1, 1 on ann
 */
beforeEach(function () {
    LiveSchema::ensure($this->db());
    LiveSchema::wipe($this->db());

    Relation::morphMap(['post' => LivePost::class, 'author' => LiveAuthor::class]);

    $nl = LiveCountry::create(['name' => 'Netherlands']);
    $be = LiveCountry::create(['name' => 'Belgium']);

    $ann = $nl->authors()->create(['name' => 'Ann']);
    $bob = $nl->authors()->create(['name' => 'Bob']);
    $cy = $be->authors()->create(['name' => 'Cy']);

    $ann->profile()->create(['bio' => 'Writes about PHP']);
    $bob->profile()->create(['bio' => 'Writes about DB2']);

    $a1 = $ann->posts()->create(['title' => 'A1', 'views' => 100]);
    $a2 = $ann->posts()->create(['title' => 'A2', 'views' => 50]);
    $a3 = $ann->posts()->create(['title' => 'A3', 'views' => 10]);
    $b1 = $bob->posts()->create(['title' => 'B1', 'views' => 5]);

    $php = LiveTag::create(['name' => 'php']);
    $db2 = LiveTag::create(['name' => 'db2']);
    $laravel = LiveTag::create(['name' => 'laravel']);

    $a1->tags()->attach([$php->id => ['weight' => 3], $db2->id => ['weight' => 1]]);
    $a2->tags()->attach($php->id, ['weight' => 2]);
    $b1->tags()->attach([$db2->id => ['weight' => 1], $laravel->id => ['weight' => 5]]);

    $a1->comments()->createMany([['body' => 'first!'], ['body' => 'nice']]);
    $ann->comments()->create(['body' => 'hi ann']);

    $this->f = (object) compact('nl', 'be', 'ann', 'bob', 'cy', 'a1', 'a2', 'a3', 'b1', 'php', 'db2', 'laravel');
    $this->db()->enableQueryLog();
});

/** Number of queries run by $fn. */
function queries(object $test, Closure $fn): int
{
    $test->db()->flushQueryLog();
    $fn();

    return count($test->db()->getQueryLog());
}

describe('has one / belongs to', function () {
    it('loads a has-one relation', function () {
        expect($this->f->ann->profile->bio)->toBe('Writes about PHP')
            ->and($this->f->cy->profile)->toBeNull();
    });

    it('loads the inverse belongs-to', function () {
        $profile = LiveProfile::where('author_id', $this->f->bob->id)->first();

        expect($profile->author->name)->toBe('Bob');
    });

    it('associates and dissociates a belongs-to', function () {
        $post = LivePost::where('title', 'B1')->first();

        $post->author()->associate($this->f->ann)->save();
        expect((int) $post->fresh()->author_id)->toBe((int) $this->f->ann->id);

        $cy = LiveAuthor::find($this->f->cy->id);
        $cy->country()->dissociate()->save();
        expect($cy->fresh()->country_id)->toBeNull();
    });

    it('updates or creates through a has-one', function () {
        $this->f->cy->profile()->updateOrCreate([], ['bio' => 'new']);
        $this->f->cy->profile()->updateOrCreate([], ['bio' => 'changed']);

        expect(LiveProfile::where('author_id', $this->f->cy->id)->count())->toBe(1)
            ->and(LiveProfile::where('author_id', $this->f->cy->id)->value('bio'))->toBe('changed');
    });

    it('picks one of many with ofMany', function () {
        $author = LiveAuthor::find($this->f->ann->id);

        expect($author->mostViewedPost->title)->toBe('A1');
    });

    it('eager loads ofMany for several parents', function () {
        $titles = LiveAuthor::with('mostViewedPost')->whereNotNull('country_id')->orderBy('id')->get()
            ->map(fn ($a) => $a->mostViewedPost?->title)->all();

        expect($titles)->toBe(['A1', 'B1', null]);
    });
});

describe('has many', function () {
    it('loads and counts children', function () {
        expect($this->f->ann->posts)->toHaveCount(3)
            ->and($this->f->ann->posts()->count())->toBe(3)
            ->and($this->f->cy->posts)->toBeEmpty();
    });

    it('creates children through the relation with the foreign key set', function () {
        $post = $this->f->cy->posts()->create(['title' => 'C1']);

        expect((int) $post->author_id)->toBe((int) $this->f->cy->id)
            ->and($this->f->cy->posts()->count())->toBe(1);
    });

    it('saves many and creates many', function () {
        $this->f->cy->posts()->saveMany([new LivePost(['title' => 'C1']), new LivePost(['title' => 'C2'])]);
        $this->f->cy->posts()->createMany([['title' => 'C3'], ['title' => 'C4']]);

        expect($this->f->cy->posts()->count())->toBe(4);
    });

    it('constrains and orders the relation query', function () {
        $titles = $this->f->ann->posts()->where('views', '>', 20)->orderBy('views')->pluck('title')->all();

        expect($titles)->toBe(['A2', 'A1']);
    });

    it('mass updates and deletes through the relation', function () {
        $this->f->ann->posts()->where('views', '<', 20)->update(['views' => 0]);
        expect($this->f->ann->posts()->where('views', 0)->count())->toBe(1);

        $this->f->ann->posts()->where('views', 0)->delete();
        expect($this->f->ann->posts()->count())->toBe(2);
    });
});

describe('eager loading', function () {
    it('loads relations in a fixed number of queries', function () {
        $authors = null;

        $count = queries($this, function () use (&$authors) {
            $authors = LiveAuthor::with(['posts', 'profile', 'country'])->orderBy('id')->get();
            // Touch every relation; nothing may trigger further queries.
            $authors->each(fn ($a) => [$a->posts->count(), $a->profile, $a->country]);
        });

        expect($count)->toBe(4)
            ->and($authors)->toHaveCount(3)
            ->and($authors[0]->posts)->toHaveCount(3)
            ->and($authors[1]->profile->bio)->toBe('Writes about DB2')
            ->and($authors[2]->country->name)->toBe('Belgium');
    });

    it('would otherwise run N+1 queries', function () {
        $count = queries($this, function () {
            LiveAuthor::orderBy('id')->get()->each(fn ($a) => $a->posts->count());
        });

        expect($count)->toBe(4); // 1 for authors + 1 per author
    });

    it('constrains an eager load', function () {
        $authors = LiveAuthor::with(['posts' => fn ($q) => $q->where('views', '>', 20)->orderBy('views', 'desc')])
            ->orderBy('id')->get();

        expect($authors[0]->posts->pluck('title')->all())->toBe(['A1', 'A2'])
            ->and($authors[1]->posts)->toBeEmpty();
    });

    it('loads nested relations', function () {
        $country = LiveCountry::with('authors.posts.tags')->where('name', 'Netherlands')->first();

        $tags = $country->authors->flatMap->posts->flatMap->tags->pluck('name')->sort()->values()->all();

        expect($tags)->toBe(['db2', 'db2', 'laravel', 'php', 'php']);
    });

    it('lazy eager loads with load and loadMissing', function () {
        $author = LiveAuthor::find($this->f->ann->id);

        expect($author->relationLoaded('posts'))->toBeFalse();

        $author->load('posts');
        expect($author->relationLoaded('posts'))->toBeTrue();

        $count = queries($this, fn () => $author->loadMissing('posts'));
        expect($count)->toBe(0);
    });

    it('selects specific columns while eager loading', function () {
        $author = LiveAuthor::with('posts:id,author_id,title')->find($this->f->ann->id);

        expect($author->posts->first()->getAttributes())->toHaveKeys(['id', 'author_id', 'title'])
            ->and($author->posts->first()->getAttributes())->not->toHaveKey('views');
    });

    it('loads aggregates with withCount, withSum and withMax', function () {
        $ann = LiveAuthor::withCount('posts')->withSum('posts', 'views')->withMax('posts', 'views')
            ->find($this->f->ann->id);

        expect((int) $ann->posts_count)->toBe(3)
            ->and((int) $ann->posts_sum_views)->toBe(160)
            ->and((int) $ann->posts_max_views)->toBe(100);
    });

    it('loads withExists', function () {
        $ann = LiveAuthor::withExists('profile')->find($this->f->ann->id);

        expect($ann->profile_exists)->toBeTrue();
    })->skip('Known limitation: Eloquent emits a bare exists(...) in the select list, which DB2 for i rejects (SQL0104). Use withCount() or has() instead.');

    it('counts a constrained relation with an alias', function () {
        $ann = LiveAuthor::withCount(['posts as popular_posts_count' => fn ($q) => $q->where('views', '>', 20)])
            ->find($this->f->ann->id);

        expect((int) $ann->popular_posts_count)->toBe(2);
    });
});

describe('relation existence queries', function () {
    it('filters with has and doesntHave', function () {
        expect(LiveAuthor::has('posts')->orderBy('id')->pluck('name')->all())->toBe(['Ann', 'Bob'])
            ->and(LiveAuthor::doesntHave('posts')->pluck('name')->all())->toBe(['Cy'])
            ->and(LiveAuthor::has('profile')->count())->toBe(2);
    });

    it('filters with has and a count operator', function () {
        expect(LiveAuthor::has('posts', '>=', 2)->pluck('name')->all())->toBe(['Ann'])
            ->and(LiveAuthor::has('posts', '=', 1)->pluck('name')->all())->toBe(['Bob']);
    });

    it('filters with whereHas and a constraint', function () {
        $names = LiveAuthor::whereHas('posts', fn ($q) => $q->where('views', '>', 60))->pluck('name')->all();

        expect($names)->toBe(['Ann']);
    });

    it('filters with whereDoesntHave and orWhereHas', function () {
        expect(LiveAuthor::whereDoesntHave('posts', fn ($q) => $q->where('views', '>', 60))->orderBy('id')->pluck('name')->all())
            ->toBe(['Bob', 'Cy'])
            ->and(LiveAuthor::whereHas('posts', fn ($q) => $q->where('title', 'B1'))
                ->orWhereHas('country', fn ($q) => $q->where('name', 'Belgium'))
                ->orderBy('id')->pluck('name')->all())->toBe(['Bob', 'Cy']);
    });

    it('filters across a belongs-to', function () {
        $titles = LivePost::whereHas('author.country', fn ($q) => $q->where('name', 'Netherlands'))->count();

        expect($titles)->toBe(4);
    });

    it('filters with whereRelation', function () {
        expect(LiveAuthor::whereRelation('profile', 'bio', 'like', '%DB2%')->pluck('name')->all())->toBe(['Bob']);
    });
});

describe('many to many', function () {
    it('loads related models and the pivot', function () {
        $tags = $this->f->a1->tags()->orderBy('name')->get();

        expect($tags->pluck('name')->all())->toBe(['db2', 'php'])
            ->and((int) $tags->firstWhere('name', 'php')->pivot->weight)->toBe(3);
    });

    it('loads the inverse side', function () {
        $titles = $this->f->db2->posts()->orderBy('title')->pluck('title')->all();

        expect($titles)->toBe(['A1', 'B1']);
    });

    it('attaches and detaches', function () {
        $this->f->a3->tags()->attach($this->f->laravel->id, ['weight' => 9]);
        expect($this->f->a3->tags()->count())->toBe(1);

        $this->f->a3->tags()->detach($this->f->laravel->id);
        expect($this->f->a3->tags()->count())->toBe(0);
    });

    it('detaches everything', function () {
        $this->f->a1->tags()->detach();

        expect($this->f->a1->tags()->count())->toBe(0)
            ->and($this->f->a2->tags()->count())->toBe(1);
    });

    it('syncs, reporting what changed', function () {
        $changes = $this->f->a1->tags()->sync([$this->f->php->id, $this->f->laravel->id]);

        expect(array_values(array_map('intval', $changes['attached'])))->toBe([(int) $this->f->laravel->id])
            ->and(array_values(array_map('intval', $changes['detached'])))->toBe([(int) $this->f->db2->id])
            ->and($this->f->a1->tags()->orderBy('name')->pluck('name')->all())->toBe(['laravel', 'php']);
    });

    it('syncs with pivot data', function () {
        $this->f->a3->tags()->sync([$this->f->php->id => ['weight' => 7]]);

        expect((int) $this->f->a3->tags()->first()->pivot->weight)->toBe(7);
    });

    it('syncs without detaching', function () {
        $this->f->a1->tags()->syncWithoutDetaching([$this->f->laravel->id]);

        expect($this->f->a1->tags()->count())->toBe(3);
    });

    it('toggles', function () {
        $this->f->a1->tags()->toggle([$this->f->php->id, $this->f->laravel->id]);

        expect($this->f->a1->tags()->orderBy('name')->pluck('name')->all())->toBe(['db2', 'laravel']);
    });

    it('updates an existing pivot row', function () {
        $this->f->a1->tags()->updateExistingPivot($this->f->php->id, ['weight' => 42]);

        expect((int) $this->f->a1->tags()->where('name', 'php')->first()->pivot->weight)->toBe(42);
    });

    it('filters on pivot columns', function () {
        $names = $this->f->b1->tags()->wherePivot('weight', '>', 2)->pluck('name')->all();

        expect($names)->toBe(['laravel']);
    });

    it('orders by a pivot column', function () {
        $names = $this->f->a1->tags()->orderByPivot('weight', 'desc')->pluck('name')->all();

        expect($names)->toBe(['php', 'db2']);
    });

    it('creates a related model through the relation', function () {
        $tag = $this->f->a3->tags()->create(['name' => 'new'], ['weight' => 4]);

        expect($this->f->a3->tags()->pluck('name')->all())->toBe(['new'])
            ->and((int) $this->f->a3->tags()->first()->pivot->weight)->toBe(4)
            ->and($tag->exists)->toBeTrue();
    });

    it('eager loads with the pivot in a fixed number of queries', function () {
        $posts = null;

        $count = queries($this, function () use (&$posts) {
            $posts = LivePost::with('tags')->orderBy('title')->get();
        });

        expect($count)->toBe(2)
            ->and($posts->firstWhere('title', 'A1')->tags->pluck('name')->sort()->values()->all())->toBe(['db2', 'php'])
            ->and($posts->firstWhere('title', 'A3')->tags)->toBeEmpty();
    });

    it('filters with whereHas, has and withCount', function () {
        expect(LivePost::whereHas('tags', fn ($q) => $q->where('name', 'laravel'))->pluck('title')->all())->toBe(['B1'])
            ->and(LivePost::has('tags', '>=', 2)->orderBy('title')->pluck('title')->all())->toBe(['A1', 'B1'])
            ->and(LivePost::doesntHave('tags')->pluck('title')->all())->toBe(['A3'])
            ->and((int) LivePost::withCount('tags')->where('title', 'A1')->first()->tags_count)->toBe(2);
    });
});

describe('has many through', function () {
    it('loads distant children through an intermediate model', function () {
        $titles = $this->f->nl->posts()->orderBy('title')->pluck('title')->all();

        expect($titles)->toBe(['A1', 'A2', 'A3', 'B1'])
            ->and($this->f->be->posts()->count())->toBe(0);
    });

    it('has one through', function () {
        expect(LiveCountry::find($this->f->nl->id)->firstPost->title)->toBe('A1');
    });

    it('eager loads and counts through relations', function () {
        $countries = LiveCountry::with('posts')->withCount('posts')->orderBy('id')->get();

        expect($countries[0]->posts)->toHaveCount(4)
            ->and((int) $countries[0]->posts_count)->toBe(4)
            ->and((int) $countries[1]->posts_count)->toBe(0);
    });

    it('filters countries with whereHas through', function () {
        expect(LiveCountry::whereHas('posts', fn ($q) => $q->where('views', '>', 90))->pluck('name')->all())->toBe(['Netherlands']);
    });
});

describe('polymorphic', function () {
    it('loads morph-many comments for different parents', function () {
        expect($this->f->a1->comments()->count())->toBe(2)
            ->and($this->f->ann->comments()->pluck('body')->all())->toBe(['hi ann'])
            ->and($this->f->a2->comments()->count())->toBe(0);
    });

    it('stores the morph map alias and the id', function () {
        $comment = LiveComment::where('body', 'hi ann')->first();

        expect($comment->commentable_type)->toBe('author')
            ->and((int) $comment->commentable_id)->toBe((int) $this->f->ann->id);
    });

    it('resolves morph-to in either direction', function () {
        $comments = LiveComment::orderBy('id')->get();

        expect($comments[0]->commentable)->toBeInstanceOf(LivePost::class)
            ->and($comments[0]->commentable->title)->toBe('A1')
            ->and($comments[2]->commentable)->toBeInstanceOf(LiveAuthor::class)
            ->and($comments[2]->commentable->name)->toBe('Ann');
    });

    it('eager loads morph-to with one query per type', function () {
        $comments = null;

        $count = queries($this, function () use (&$comments) {
            $comments = LiveComment::with('commentable')->orderBy('id')->get();
        });

        expect($count)->toBe(3) // comments + posts + authors
            ->and($comments->pluck('commentable')->filter()->count())->toBe(3);
    });

    it('filters with whereHasMorph', function () {
        $bodies = LiveComment::whereHasMorph('commentable', [LivePost::class], fn ($q) => $q->where('title', 'A1'))
            ->orderBy('id')->pluck('body')->all();

        expect($bodies)->toBe(['first!', 'nice']);
    });

    it('counts morph-many and filters with has', function () {
        expect(LivePost::has('comments')->pluck('title')->all())->toBe(['A1'])
            ->and((int) LivePost::withCount('comments')->where('title', 'A1')->first()->comments_count)->toBe(2);
    });
});

describe('foreign keys', function () {
    it('rejects a child row whose parent does not exist', function () {
        $parent = $this->tempTable('FP');
        $child = $this->tempTable('FC');
        $schema = $this->db()->getSchemaBuilder();

        try {
            $schema->create($parent, function (Blueprint $t) {
                $t->increments('id');
                $t->string('name', 10)->nullable();
            });
            $schema->create($child, function (Blueprint $t) use ($parent) {
                $t->integer('id');
                $t->unsignedInteger('parent_id');
                $t->foreign('parent_id')->references('id')->on($parent);
            });

            $parentId = $this->db()->table($parent)->insertGetId(['name' => 'p']);
            $this->db()->table($child)->insert(['id' => 1, 'parent_id' => $parentId]);

            expect($this->db()->table($child)->count())->toBe(1);

            $this->db()->table($child)->insert(['id' => 2, 'parent_id' => 999999]);
        } finally {
            $schema->dropIfExists($child);
            $schema->dropIfExists($parent);
        }
    })->throws(\Illuminate\Database\QueryException::class, 'SQL0530');

    $journal = 'Changing or deleting a foreign key parent row needs journaled tables (a QSQJRN journal in DB_SCHEMA); without it DB2 for i fails with SQL7008.';

    it('refuses to delete a parent that still has children', function () {
        // Needs foreign keys on LiveSchema's tables (default NO ACTION rule).
        $this->f->ann->delete();
    })->skip($journal)->throws(\Illuminate\Database\QueryException::class);

    it('cascades deletes to children and the pivot', function () {
        // Needs ->onDelete('cascade') foreign keys on LiveSchema's tables.
        $this->f->ann->delete();

        expect(LivePost::where('author_id', $this->f->ann->id)->count())->toBe(0);
    })->skip($journal);

    it('sets the foreign key to null when the parent is deleted', function () {
        // Needs ->onDelete('set null') on authors.country_id in LiveSchema.
        $this->f->be->delete();

        expect(LiveAuthor::find($this->f->cy->id)->country_id)->toBeNull();
    })->skip($journal);
});
