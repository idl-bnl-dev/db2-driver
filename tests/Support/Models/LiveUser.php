<?php

namespace IDL\DB2Driver\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

/**
 * Model used by the live feature tests. The table name is set per test
 * (see EloquentLiveTest) so each test works on its own throwaway table.
 */
class LiveUser extends Model
{
    use SoftDeletes;

    protected $connection = 'db2';

    protected $guarded = [];

    protected function casts(): array
    {
        return [
            'age' => 'integer',
            'active' => 'boolean',
            'settings' => 'array',
        ];
    }
}
