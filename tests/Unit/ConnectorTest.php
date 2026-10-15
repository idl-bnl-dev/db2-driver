<?php

use IDL\DB2Driver\DB2Connector;

function db2Config(array $overrides = []): array
{
    return $overrides + [
        'driverName' => '{IBM i Access ODBC Driver}',
        'host' => 'ibmi.example.com',
        'port' => 446,
        'database' => 'MYDB',
        'username' => 'user',
        'password' => 'secret',
    ];
}

it('builds the base ODBC DSN', function () {
    expect((new DB2Connector)->getDsn(db2Config()))->toBe(
        'odbc:DRIVER={IBM i Access ODBC Driver};System=ibmi.example.com;Port=446;Database=MYDB;UserID=user;Password=secret'
    );
});

it('appends ODBC keywords', function () {
    $dsn = (new DB2Connector)->getDsn(db2Config([
        'odbc_keywords' => ['Naming' => 1, 'CommitMode' => 2],
    ]));

    expect($dsn)->toEndWith(';Naming=1;CommitMode=2');
});

it('sets the schema after connecting', function () {
    $statement = Mockery::mock(PDOStatement::class);
    $statement->shouldReceive('execute')->once();

    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldReceive('prepare')->once()->with('set schema MYLIB')->andReturn($statement);

    $connector = new class($pdo) extends DB2Connector
    {
        public ?string $dsn = null;

        public function __construct(private PDO $pdo) {}

        public function createConnection($dsn, array $config, array $options)
        {
            $this->dsn = $dsn;

            return $this->pdo;
        }
    };

    expect($connector->connect(db2Config(['schema' => 'MYLIB'])))->toBe($pdo)
        ->and($connector->dsn)->toStartWith('odbc:DRIVER=');
});

it('skips set schema when none is configured', function () {
    $pdo = Mockery::mock(PDO::class);
    $pdo->shouldNotReceive('prepare');

    $connector = new class($pdo) extends DB2Connector
    {
        public function __construct(private PDO $pdo) {}

        public function createConnection($dsn, array $config, array $options)
        {
            return $this->pdo;
        }
    };

    $connector->connect(db2Config(['schema' => '']));
});
