<?php

namespace IDL\DB2Driver\Tests\Support;

use IDL\DB2Driver\DB2Connection;
use Illuminate\Database\Schema\Blueprint;

/**
 * The tables deliberately have no foreign key constraints: on IBM i, deleting or updating a
 * parent row of a foreign key needs journaled tables (SQL7008 otherwise), and these tables are
 * wiped between tests. See the "foreign keys" tests for the constraint behaviour.
 *
 * A small blog-shaped schema (countries, authors, profiles, posts, tags, comments)
 * created once per test run on the live database and dropped again at shutdown.
 * Table names carry a unique prefix so they can never collide with real data.
 */
class LiveSchema
{
    private static ?string $prefix = null;

    private static ?DB2Connection $connection = null;

    /** Parent tables first; children are dropped in the reverse order. */
    private const TABLES = ['countries', 'authors', 'profiles', 'posts', 'tags', 'post_tag', 'comments'];

    public static function table(string $name): string
    {
        return (self::$prefix ??= strtoupper('PHPT_R_'.bin2hex(random_bytes(3))).'_').strtoupper($name);
    }

    public static function ensure(DB2Connection $connection): void
    {
        if (self::$connection !== null) {
            return;
        }

        self::$connection = $connection;
        register_shutdown_function([self::class, 'drop']);

        $schema = $connection->getSchemaBuilder();

        $schema->create(self::table('countries'), function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 50);
        });

        $schema->create(self::table('authors'), function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('country_id')->nullable();
            $t->string('name', 50);
        });

        $schema->create(self::table('profiles'), function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('author_id');
            $t->string('bio', 200)->nullable();
        });

        $schema->create(self::table('posts'), function (Blueprint $t) {
            $t->increments('id');
            $t->unsignedInteger('author_id');
            $t->string('title', 100);
            $t->integer('views')->default(0);
        });

        $schema->create(self::table('tags'), function (Blueprint $t) {
            $t->increments('id');
            $t->string('name', 50);
        });

        $schema->create(self::table('post_tag'), function (Blueprint $t) {
            $t->unsignedInteger('post_id');
            $t->unsignedInteger('tag_id');
            $t->integer('weight')->default(0);
        });

        $schema->create(self::table('comments'), function (Blueprint $t) {
            $t->increments('id');
            $t->string('commentable_type', 50);
            $t->unsignedInteger('commentable_id');
            $t->string('body', 200);
        });
    }

    /** Remove all rows so each test starts from an empty schema. */
    public static function wipe(DB2Connection $connection): void
    {
        foreach (array_reverse(self::TABLES) as $name) {
            $connection->table(self::table($name))->delete();
        }
    }

    public static function drop(): void
    {
        if (self::$connection === null) {
            return;
        }

        $schema = self::$connection->getSchemaBuilder();

        foreach (array_reverse(self::TABLES) as $name) {
            try {
                $schema->dropIfExists(self::table($name));
            } catch (\Throwable) {
                // Keep dropping the rest; a leftover is better than an aborted cleanup.
            }
        }

        self::$connection = null;
    }
}
