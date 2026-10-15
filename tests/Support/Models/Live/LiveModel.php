<?php

namespace IDL\DB2Driver\Tests\Support\Models\Live;

use IDL\DB2Driver\Tests\Support\LiveSchema;
use Illuminate\Database\Eloquent\Model;

abstract class LiveModel extends Model
{
    protected $connection = 'db2';

    public $timestamps = false;

    protected $guarded = [];

    /** Short table name inside LiveSchema, e.g. "authors". */
    abstract protected static function liveTable(): string;

    public function getTable()
    {
        return LiveSchema::table(static::liveTable());
    }
}
