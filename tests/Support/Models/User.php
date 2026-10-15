<?php

namespace IDL\DB2Driver\Tests\Support\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $connection = 'db2';

    protected $table = 'users';

    public $timestamps = false;

    protected $guarded = [];
}
