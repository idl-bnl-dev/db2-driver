<?php

it('upper-cases the default schema', function () {
    expect(db2(['schema' => 'mylib'])->getDefaultSchema())->toBe('MYLIB');
});

it('sets the schema through a prepared statement', function () {
    $connection = db2();
    $connection->setCurrentSchema('other');

    expect($connection->statements)->toBe([
        ['query' => 'SET SCHEMA ?', 'bindings' => ['OTHER']],
    ]);
});

it('resets an empty schema to DEFAULT', function () {
    $connection = db2();
    $connection->setCurrentSchema('');

    expect($connection->statements[0]['bindings'])->toBe(['DEFAULT']);
});

it('resets to the default schema', function () {
    $connection = db2(['schema' => 'mylib']);
    $connection->resetCurrentSchema();

    expect($connection->statements[0]['bindings'])->toBe(['MYLIB']);
});

it('runs system commands through QCMDEXC', function () {
    $connection = db2();
    $connection->executeCommand('CHGJOB INQMSGRPY(*SYSRPYL)');

    expect($connection->statements)->toBe([
        ['query' => 'CALL QSYS2.QCMDEXC(?)', 'bindings' => ['CHGJOB INQMSGRPY(*SYSRPYL)']],
    ]);
});

it('exposes the DB2 grammars and processor', function () {
    $connection = db2();

    expect($connection->getQueryGrammar())->toBeInstanceOf(\IDL\DB2Driver\DB2QueryGrammar::class)
        ->and($connection->getPostProcessor())->toBeInstanceOf(\IDL\DB2Driver\DB2Processor::class)
        ->and($connection->getSchemaBuilder())->toBeInstanceOf(\IDL\DB2Driver\Schema\DB2Builder::class)
        ->and($connection->getSchemaBuilder()->getConnection()->getSchemaGrammar())
        ->toBeInstanceOf(\IDL\DB2Driver\Schema\DB2SchemaGrammar::class);
});

it('is resolved for the db2 driver', function () {
    app()->register(\IDL\DB2Driver\DB2ServiceProvider::class);

    // Connection::resolverFor() is invoked lazily, so we only check registration here.
    expect(\Illuminate\Database\Connection::getResolver('db2'))->toBeCallable();
});

it('has a schema grammar on a connection built outside the fake', function () {
    $connection = new \IDL\DB2Driver\DB2Connection(new PDO('sqlite::memory:'), 'TESTDB', '', ['schema' => 'TESTLIB']);

    expect($connection->getSchemaGrammar())->toBeInstanceOf(\IDL\DB2Driver\Schema\DB2SchemaGrammar::class)
        ->and($connection->getSchemaBuilder())->toBeInstanceOf(\IDL\DB2Driver\Schema\DB2Builder::class);
});
