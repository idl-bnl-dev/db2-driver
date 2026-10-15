<?php

namespace IDL\DB2Driver;

use Illuminate\Database\Connection;
use Illuminate\Support\ServiceProvider;

class DB2ServiceProvider extends ServiceProvider
{
    public function register()
    {
        /**
         * Override any database connections using the 'db2' driver.
         */
        Connection::resolverFor('db2', function ($connection, $database, $prefix, $config) {
            // Package-level defaults; per-connection config takes precedence.
            $config += [
                'readonly' => config('db2-driver.readonly', false),
                'readonly_allow_updates' => config('db2-driver.readonly_allow_updates', false),
            ];

            $connector = new DB2Connector();

            $connection = new DB2Connection(
                $connector->connect($config),
                $database,
                $prefix,
                $config
            );

            return $connection;
        });
    }
}
