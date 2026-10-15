[<img src="https://banners.beyondco.de/db2-driver.png?theme=dark&packageManager=composer+require&packageName=idl-bnl-dev%2Fdb2-driver&pattern=bankNote&style=style_1&description=DB2+for+IBM+iSeries+connectivity+for+Laravel&md=1&showWatermark=0&fontSize=100px&images=server&widths=auto"/>]()

# DB2 for IBM iSeries Laravel Driver
This package allows you to use Laravel's query builder and eloquent with DB2 for IBM iSeries by extending the Illuminate Database component of the Laravel framework. Originally forked from [cooperl22/laravel-db2](https://github.com/cooperl22/laravel-db2).

> **Private package:** This package is maintained internally by ID Logistics and is not published on Packagist. Access to the [GitHub repository](https://github.com/idl-bnl-dev/db2-driver) is required.

## Requirements
- PHP PDO_ODBC extension
- IBM i Access ODBC Driver ([Windows & Linux](https://ibmi-oss-docs.readthedocs.io/en/latest/odbc/installation.html))

## Installation

Since the package is private, first register the GitHub repository as a VCS source in your project's `composer.json`:

```json
"repositories": [
    {
        "type": "vcs",
        "url": "git@github.com:idl-bnl-dev/db2-driver.git"
    }
]
```

> Note: Composer needs access to the repository, either through an SSH key or a GitHub token (`composer config --global github-oauth.github.com <token>`) with read access to `idl-bnl-dev/db2-driver`.

Then install the package via composer:

```bash
composer require idl-bnl-dev/db2-driver
```

Add a new connection in `database.php`:
> Note: You can specify the connection name, but the driver must be 'db2'
```php
'myDB2Connection' => [
    'driver'        => 'db2',
    'driverName'    => '{IBM i Access ODBC Driver}',
    'host'          => env('DB_HOST'),
    'username'      => env('DB_USERNAME'),
    'password'      => env('DB_PASSWORD'),
    'database'      => env('DB_DATABASE'),
    'prefix'        => '',
    'schema'        => env('DB_SCHEMA'),
    'port'          => env('DB_PORT', 50000),
    'date_format'   => 'Y-m-d H:i:s', // or 'Y-m-d H:i:s.u' / 'Y-m-d-H.i.s.u'
    'offset_compatibility_mode' => true,
    'odbc_keywords' => [
        'SIGNON'                => 3,
        'SSL'                   => 0,
        'CommitMode'            => 0,
        'ConnectionType'        => 0,
        'DefaultLibraries'      => '',
        'Naming'                => 1,
        'UNICODESQL'            => 0,
        'DateFormat'            => 5,
        'DateSeperator'         => 0,
        'Decimal'               => 0,
        'TimeFormat'            => 0,
        'TimeSeparator'         => 0,
        'TimestampFormat'       => 0,
        'ConvertDateTimeToChar' => 0,
        'BLOCKFETCH'            => 1,
        'BlockSizeKB'           => 32,
        'AllowDataCompression'  => 1,
        'CONCURRENCY'           => 0,
        'LAZYCLOSE'             => 0,
        'MaxFieldLength'        => 15360,
        'PREFETCH'              => 0,
        'QUERYTIMEOUT'          => 1,
        'DefaultPkgLibrary'     => 'QGPL',
        'DefaultPackage'        => 'A /DEFAULT(IBM),2,0,1,0',
        'ExtendedDynamic'       => 1,
        'QAQQINILibrary'        => '',
        'SQDIAGCODE'            => '',
        'LANGUAGEID'            => 'ENU',
        'SORTTABLE'             => '',
        'SortSequence'          => 0,
        'SORTWEIGHT'            => 0,
        'AllowUnsupportedChar'  => 0,
        'CCSID'                 => 1208,
        'GRAPHIC'               => 0,
        'ForceTranslation'      => 0,
        'ALLOWPROCCALLS'        => 0,
        'DB2SQLSTATES'          => 0,
        'DEBUG'                 => 0,
        'TRUEAUTOCOMMIT'        => 0,
        'CATALOGOPTIONS'        => 3,
        'LibraryView'           => 0,
        'ODBCRemarks'           => 0,
        'SEARCHPATTERN'         => 1,
        'TranslationDLL'        => '',
        'TranslationOption'     => 0,
        'MAXTRACESIZE'          => 0,
        'MultipleTraceFiles'    => 1,
        'TRACE'                 => 0,
        'TRACEFILENAME'         => '',
        'ExtendedColInfo'       => 0,
    ],
    'options' => [
        PDO::ATTR_CASE             => PDO::CASE_LOWER,
        PDO::ATTR_EMULATE_PREPARES => false,
        PDO::ATTR_PERSISTENT       => false,
    ]
],
```

> Note: Be sure to define the appropriate keys in `.env`.

### Read-only mode
Publish the package config:

```bash
php artisan vendor:publish --tag=db2-driver-config
```

Then set `DB2_READONLY=true` (or `'readonly' => true` in `config/db2-driver.php`) to block all writes. Inserts, updates/merges, deletes and schema
changes (`CREATE`, `ALTER`, `DROP`, `RENAME`, `TRUNCATE`) throw `IDL\DB2Driver\Exceptions\ReadOnlyModeException`
before reaching the database. Selects are unaffected.

Set `DB2_READONLY_ALLOW_UPDATES=true` as well to permit `UPDATE`/`MERGE` statements while everything else stays blocked.

These are defaults for every `db2` connection; `readonly` and `readonly_allow_updates` keys on a connection in `config/database.php` override them.

## Other Resources
- [DB2 Connection String Keywords](https://www.ibm.com/docs/fr/i/7.3?topic=details-connection-string-keywords)
- [PDO Attributes](https://www.w3resource.com/php/pdo/php-pdo.php)
- [ACS / IBM i Access Driver Release Notes](https://www.ibm.com/support/pages/ibm-i-access-acs-updates-pase)
- [Seiden Group](https://www.seidengroup.com/blog/)

## License
This package is released under the MIT License. Please see [License File](LICENSE.md) for more information.