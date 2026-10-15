<?php

use IDL\DB2Driver\DB2Connection;
use IDL\DB2Driver\Exceptions\ReadOnlyModeException;

function roConnection(array $config = []): DB2Connection
{
    return new DB2Connection(new PDO('sqlite::memory:'), 'TESTDB', '', $config + ['name' => 'db2']);
}

it('allows everything when read-only is off', function () {
    $c = roConnection();
    $c->unprepared('create table t (a integer)');
    $c->insert('insert into t (a) values (1)');

    expect($c->update('update t set a = 2'))->toBe(1);
    expect($c->delete('delete from t'))->toBe(1);
});

it('blocks writes in read-only mode', function (string $sql, string $type) {
    $c = roConnection(['readonly' => true]);

    try {
        $c->unprepared($sql);
        $this->fail('Expected exception');
    } catch (ReadOnlyModeException $e) {
        expect($e->operation)->toBe($type);
    }
})->with([
    ['create table t (a integer)', 'schema'],
    ['  DROP TABLE t', 'schema'],
    ['/* x */ insert into t values (1)', 'insert'],
    ['update t set a = 1', 'update'],
    ['delete from t', 'delete'],
]);

it('still allows selects in read-only mode', function () {
    expect(roConnection(['readonly' => true])->select('select 1 as a')[0]->a)->toBe(1);
});

it('can allow updates separately', function () {
    $setup = new PDO('sqlite::memory:');
    $setup->exec('create table t (a integer)');
    $setup->exec('insert into t values (1)');
    $c = new DB2Connection($setup, 'TESTDB', '', ['readonly' => true, 'readonly_allow_updates' => true]);

    expect($c->update('update t set a = 2'))->toBe(1);
    expect(fn () => $c->insert('insert into t values (3)'))->toThrow(ReadOnlyModeException::class);
    expect(fn () => $c->delete('delete from t'))->toThrow(ReadOnlyModeException::class);
});

it('blocks Eloquent-style builder writes', function () {
    $c = roConnection(['readonly' => true]);

    expect(fn () => $c->table('t')->insert(['a' => 1]))->toThrow(ReadOnlyModeException::class);
});

it('publishes a config file with read-only defaults', function () {
    expect(config('db2-driver.readonly'))->toBeFalse()
        ->and(config('db2-driver.readonly_allow_updates'))->toBeFalse();
});

it('blocks inserts that fetch the generated id', function () {
    $c = roConnection(['readonly' => true]);

    expect(fn () => $c->table('t')->insertGetId(['a' => 1]))->toThrow(ReadOnlyModeException::class);
    expect(fn () => $c->select('select id from new table (insert into t (a) values (1))'))
        ->toThrow(ReadOnlyModeException::class);
});

it('blocks Eloquent create/save/delete and schema builder calls', function () {
    $c = roConnection(['readonly' => true]);

    expect(fn () => $c->getSchemaBuilder()->create('t', fn ($t) => $t->integer('a')))
        ->toThrow(ReadOnlyModeException::class);
    expect(fn () => $c->table('t')->where('a', 1)->update(['a' => 2]))->toThrow(ReadOnlyModeException::class);
    expect(fn () => $c->table('t')->where('a', 1)->delete())->toThrow(ReadOnlyModeException::class);
});
