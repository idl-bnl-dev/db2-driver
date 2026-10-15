<?php

use IDL\DB2Driver\DB2Connection;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

uses()->group('live');

beforeEach(function () {
    $this->table = $this->tempTable();
});

afterEach(function () {
    // Always clean up, even when an assertion failed mid-test.
    if (isset($this->app)) {
        $this->db()->getSchemaBuilder()->dropIfExists($this->table);
    }
});

it('connects and runs a query', function () {
    expect($this->db())->toBeInstanceOf(DB2Connection::class);

    $row = $this->db()->selectOne('select 1 as one from sysibm.sysdummy1');

    expect((int) $row->one)->toBe(1);
});

it('applies the configured default schema', function () {
    $row = $this->db()->selectOne('select current schema as schema_name from sysibm.sysdummy1');

    expect(trim($row->schema_name))->toBe(strtoupper(env('DB_SCHEMA')));
});

describe('schema', function () {
    it('creates, inspects and drops a table', function () {
        $schema = $this->db()->getSchemaBuilder();

        expect($schema->hasTable($this->table))->toBeFalse();

        $schema->create($this->table, function (Blueprint $table) {
            $table->integer('id');
            $table->string('name', 50)->nullable();
            $table->primary('id');
        });

        expect($schema->hasTable($this->table))->toBeTrue()
            ->and(array_map('strtolower', $schema->getColumnListing($this->table)))->toBe(['id', 'name']);

        $schema->drop($this->table);

        expect($schema->hasTable($this->table))->toBeFalse();
    });
});

describe('query builder', function () {
    beforeEach(function () {
        $this->db()->getSchemaBuilder()->create($this->table, function (Blueprint $table) {
            $table->integer('id');
            $table->string('name', 50)->nullable();
            $table->primary('id');
        });
    });

    it('inserts, selects, updates and deletes rows', function () {
        $query = fn () => $this->db()->table($this->table);

        $query()->insert([
            ['id' => 1, 'name' => 'alpha'],
            ['id' => 2, 'name' => 'beta'],
            ['id' => 3, 'name' => 'gamma'],
        ]);

        expect($query()->count())->toBe(3)
            ->and($query()->where('id', 2)->value('name'))->toBe('beta')
            ->and($query()->orderBy('id', 'desc')->pluck('name')->all())->toBe(['gamma', 'beta', 'alpha']);

        expect($query()->where('id', 2)->update(['name' => 'BETA']))->toBe(1)
            ->and($query()->where('id', 2)->value('name'))->toBe('BETA');

        expect($query()->where('id', 3)->delete())->toBe(1)
            ->and($query()->count())->toBe(2);
    });

    it('limits and offsets results', function () {
        $query = fn () => $this->db()->table($this->table)->orderBy('id');

        $this->db()->table($this->table)->insert(
            array_map(fn ($i) => ['id' => $i, 'name' => "row {$i}"], range(1, 10))
        );

        // PDO_ODBC returns integers as strings, so compare numerically.
        $ids = fn ($builder) => $builder->pluck('id')->map(fn ($id) => (int) $id)->all();

        expect($ids($query()->limit(3)))->toBe([1, 2, 3])
            ->and($ids($query()->offset(3)->limit(3)))->toBe([4, 5, 6])
            ->and($ids($query()->forPage(4, 3)))->toBe([10]);
    });

    it('rolls back a transaction', function () {
        $this->db()->beginTransaction();
        $this->db()->table($this->table)->insert(['id' => 1, 'name' => 'temp']);
        $this->db()->rollBack();

        expect($this->db()->table($this->table)->count())->toBe(0);
    })->skip('Needs a journal (QSQJRN) in DB_SCHEMA so tables are journaled; without it commit control fails with SQL7008.');
});
