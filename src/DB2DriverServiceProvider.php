<?php

namespace IDL\DB2Driver;

use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class DB2DriverServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        $package->name('db2-driver')
            ->hasConfigFile()
            ->publishesServiceProvider('DB2ServiceProvider');
    }
}
