<?php

use IDL\DB2Driver\Tests\Support\FakeDB2Connection;
use IDL\DB2Driver\Tests\TestCase;

uses(TestCase::class)->in(__DIR__);

/**
 * Build a DB2 connection that never touches a real database.
 */
function db2(array $config = []): FakeDB2Connection
{
    return new FakeDB2Connection($config);
}
