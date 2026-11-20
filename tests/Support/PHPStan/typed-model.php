<?php

namespace IDL\DB2Driver\Tests\Support\PHPStan;

use IDL\DB2Driver\Eloquent\HasColumnAliases;
use IDL\DB2Driver\Tests\Support\Models\AliasedOrder;
use Illuminate\Database\Eloquent\Model;

class Casted extends Model
{
    use HasColumnAliases;

    protected $casts = ['A' => 'int', 'B' => 'decimal:2', 'C' => 'boolean', 'D' => 'json', 'E' => 'datetime', 'F' => 'custom', 'H' => 'immutable_datetime:Y-m-d'];

    protected array $columnAliases = [
        'a_int' => 'A', 'b_decimal' => 'B', 'c_bool' => 'C', 'd_json' => 'D', 'e_date' => 'E', 'f_other' => 'F', 'g_plain' => 'G', 'h_immutable' => 'H',
    ];
}

class ChildOrder extends AliasedOrder {}

function usage(AliasedOrder $order, ChildOrder $child, Casted $casted): void
{
    \PHPStan\dumpType($order->ordered_quantity);
    \PHPStan\dumpType($order->order_status);
    \PHPStan\dumpType($child->ordered_quantity);
    \PHPStan\dumpType($casted->a_int);
    \PHPStan\dumpType($casted->b_decimal);
    \PHPStan\dumpType($casted->c_bool);
    \PHPStan\dumpType($casted->d_json);
    \PHPStan\dumpType($casted->e_date);
    \PHPStan\dumpType($casted->f_other);
    \PHPStan\dumpType($casted->g_plain);
    $order->ordered_quantity = 3.5;
    $order->orderedQuantity;
    $order->not_an_alias;
    \PHPStan\dumpType($casted->h_immutable);
}
