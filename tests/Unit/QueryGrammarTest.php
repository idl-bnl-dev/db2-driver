<?php

it('compiles a plain select', function () {
    expect(db2()->table('users')->where('id', 1)->toSql())
        ->toBe('select * from users where id = ?');
});

it('does not quote identifiers', function () {
    expect(db2()->table('users')->select('a', 'b')->orderBy('a')->toSql())
        ->toBe('select a, b from users order by a asc');
});

it('uses FETCH FIRST for limit in compatibility mode', function () {
    expect(db2()->table('users')->where('id', 1)->limit(10)->toSql())
        ->toBe('select * from users where id = ? FETCH FIRST 10 ROWS ONLY');
});

it('(offset compat) wraps the query in row_number() for offset + limit', function () {
    expect(db2()->table('users')->orderBy('name')->limit(10)->offset(5)->toSql())
        ->toBe('select * from (select users.*,  row_number() over (order by name asc) as row_num from users) as temp_table where row_num between 6 and 15');
});

it('(offset compat) adds a dummy ORDER BY when none is given', function () {
    expect(db2()->table('users')->limit(10)->offset(5)->toSql())
        ->toContain('row_number() over (order by 1) as row_num')
        ->toContain('row_num between 6 and 15');
});

it('(offset compat) handles offset without limit', function () {
    expect(db2()->table('users')->offset(5)->toSql())
        ->toEndWith('where row_num >= 6');
});

it('(offset compat) keeps selected columns and wheres inside the wrapped query', function () {
    expect(db2()->table('users')->select('a', 'b')->where('x', '>', 1)->orderBy('a')->offset(2)->toSql())
        ->toBe('select * from (select a, b,  row_number() over (order by a asc) as row_num from users where x > ?) as temp_table where row_num >= 3');
});

it('(offset compat) moves order-by bindings into the select bindings', function () {
    $query = db2()->table('users')
        ->where('active', 1)
        ->orderByRaw('case when status = ? then 0 else 1 end', ['new'])
        ->limit(5)->offset(5);

    $query->toSql();

    expect($query->getRawBindings()['order'])->toBe([])
        ->and($query->getRawBindings()['select'])->toBe(['new']);
});

it('uses standard limit/offset when offset compatibility mode is off', function () {
    expect(db2(['offset_compatibility_mode' => false])->table('users')->limit(10)->offset(5)->toSql())
        ->toBe('select * from users limit 10 offset 5');
});

it('compiles exists as a one row select', function () {
    $query = db2()->table('users')->where('id', 1);

    expect($query->getGrammar()->compileExists($query))
        ->toBe('select 1 exists from users where id = ? FETCH FIRST 1 ROWS ONLY');
});

it('does not mutate the original query when compiling exists', function () {
    $query = db2()->table('users');
    $query->getGrammar()->compileExists($query);

    expect($query->columns)->toBeNull();
});

it('uses the configured date format', function () {
    $connection = db2(['date_format' => 'Y-m-d H:i:s.u']);

    expect($connection->getQueryGrammar()->getDateFormat())->toBe('Y-m-d H:i:s.u');
});

it('falls back to the default date format', function () {
    expect(db2()->getQueryGrammar()->getDateFormat())->toBe('Y-m-d H:i:s');
});

it('compiles savepoints with ON ROLLBACK RETAIN CURSORS', function () {
    expect(db2()->getQueryGrammar()->compileSavepoint('trans2'))
        ->toBe('SAVEPOINT trans2 ON ROLLBACK RETAIN CURSORS');
});
