<?php

namespace BWICompanies\DB2Driver\Tests\Support;

use BWICompanies\DB2Driver\DB2Connection;
use PDO;

/**
 * A DB2Connection that never talks to a database.
 *
 * It is backed by an in-memory SQLite PDO purely to satisfy the constructor.
 * select() and statement() are recorded (and select() is stubbed) so the SQL
 * that the driver generates can be asserted without a live IBM i.
 */
class FakeDB2Connection extends DB2Connection
{
    /** @var array<int, array{query: string, bindings: array}> */
    public array $selects = [];

    /** @var array<int, array{query: string, bindings: array}> */
    public array $statements = [];

    /** @var array<int, mixed> Rows returned by consecutive select() calls. */
    public array $selectResults = [];

    public function __construct(array $config = [])
    {
        parent::__construct(
            new PDO('sqlite::memory:'),
            $config['database'] ?? 'TESTDB',
            $config['prefix'] ?? '',
            $config + ['schema' => 'TESTLIB', 'name' => 'db2']
        );

        $this->useDefaultSchemaGrammar();
    }

    public function select($query, $bindings = [], $useReadPdo = true, array $fetchUsing = [])
    {
        $this->selects[] = ['query' => $query, 'bindings' => $bindings];

        return array_shift($this->selectResults) ?? [];
    }

    public function statement($query, $bindings = [])
    {
        $this->statements[] = ['query' => $query, 'bindings' => $bindings];

        return true;
    }
}
