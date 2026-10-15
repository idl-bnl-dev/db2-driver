<?php

namespace IDL\DB2Driver\Tests\Live;

use Dotenv\Dotenv;
use IDL\DB2Driver\DB2Connection;
use IDL\DB2Driver\DB2ServiceProvider;
use IDL\DB2Driver\Tests\TestCase;

/**
 * Base class for tests that run against a real IBM i DB2 database.
 *
 * Credentials come from the environment (repo secrets in CI) or from a local
 * .env file at the package root. Tests are skipped when they are missing.
 */
abstract class LiveTestCase extends TestCase
{
    private const REQUIRED = ['DB_HOST', 'DB_PORT', 'DB_DATABASE', 'DB_SCHEMA', 'DB_USERNAME', 'DB_PASSWORD'];

    protected function setUp(): void
    {
        Dotenv::createImmutable(dirname(__DIR__, 2))->safeLoad();

        foreach (self::REQUIRED as $key) {
            if (($_ENV[$key] ?? getenv($key) ?: '') === '') {
                $this->markTestSkipped("Live DB2 tests need {$key} (set it in .env or the environment).");
            }
        }

        if (! extension_loaded('pdo_odbc')) {
            $this->markTestSkipped('Live DB2 tests need the pdo_odbc extension.');
        }

        parent::setUp();
    }

    protected function getPackageProviders($app)
    {
        return [...parent::getPackageProviders($app), DB2ServiceProvider::class];
    }

    public function getEnvironmentSetUp($app)
    {
        parent::getEnvironmentSetUp($app);

        $env = fn (string $key) => $_ENV[$key] ?? getenv($key);

        config()->set('database.connections.db2', [
            'driver' => 'db2',
            'driverName' => $env('DB2_ODBC_DRIVER') ?: '{IBM i Access ODBC Driver}',
            'host' => $env('DB_HOST'),
            'port' => $env('DB_PORT'),
            'database' => $env('DB_DATABASE'),
            'schema' => $env('DB_SCHEMA'),
            'username' => $env('DB_USERNAME'),
            'password' => $env('DB_PASSWORD'),
            'prefix' => '',
            'date_format' => 'Y-m-d H:i:s',
            'offset_compatibility_mode' => true,
            'options' => [
                \PDO::ATTR_CASE => \PDO::CASE_LOWER,
                \PDO::ATTR_EMULATE_PREPARES => false,
                \PDO::ATTR_PERSISTENT => false,
            ],
        ]);
    }

    public function db(): DB2Connection
    {
        /** @var DB2Connection */
        return $this->app['db']->connection('db2');
    }

    /**
     * A unique table name so parallel/aborted runs never collide with real data.
     */
    protected function tempTable(string $suffix = 'T'): string
    {
        return strtoupper('PHPT_'.$suffix.'_'.bin2hex(random_bytes(4)));
    }
}
