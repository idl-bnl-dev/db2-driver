<?php

namespace IDL\DB2Driver\Tests\Support\Models;

use IDL\DB2Driver\Eloquent\HasColumnAliases;
use Illuminate\Database\Eloquent\Model;

class AliasedOrder extends Model
{
    use HasColumnAliases;

    protected $connection = 'db2';

    protected $table = 'ORDERS';

    public $timestamps = false;

    protected $fillable = ['UVCCDE', 'STATUS'];

    protected $casts = ['UVCCDE' => 'float'];

    protected array $columnAliases = [
        'ordered_quantity' => 'UVCCDE',
        'order_status' => 'STATUS',
    ];

    public bool $exposeAliases = false;
}
