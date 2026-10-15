<?php

it('wraps inserts in "select from new table" to get the id', function () {
    $connection = db2();
    $connection->selectResults = [[(object) ['ID' => '42']]];

    $id = $connection->table('users')->insertGetId(['name' => 'x']);

    expect($id)->toBe(42)
        ->and($connection->selects[0]['query'])->toBe('select id from new table (insert into users (name) values (?))')
        ->and($connection->selects[0]['bindings'])->toBe(['x']);
});

it('uses the given sequence column', function () {
    $connection = db2();
    $connection->selectResults = [[(object) ['user_id' => 7]]];

    $id = $connection->table('users')->insertGetId(['name' => 'x'], 'user_id');

    expect($id)->toBe(7)
        ->and($connection->selects[0]['query'])->toStartWith('select user_id from new table (');
});

it('returns non numeric ids untouched', function () {
    $connection = db2();
    $connection->selectResults = [[(object) ['id' => 'abc']]];

    expect($connection->table('users')->insertGetId(['name' => 'x']))->toBe('abc');
});

it('returns all values when the sequence is an array', function () {
    $connection = db2();
    $connection->selectResults = [[(object) ['a' => 1, 'b' => 2]]];

    $ids = (new \IDL\DB2Driver\DB2Processor)->processInsertGetId(
        $connection->table('users'), 'insert into users (name) values (?)', ['x'], ['a', 'b']
    );

    expect($ids)->toBe([1, 2])
        ->and($connection->selects[0]['query'])->toStartWith('select a, b from new table (');
});
