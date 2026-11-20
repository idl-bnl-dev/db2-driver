<?php

namespace IDL\DB2Driver\Tests\Support\PHPStan;

use IDL\DB2Driver\Tests\Support\Models\AliasedOrder;
use IDL\DB2Driver\Tests\Support\Models\User;

function rules(AliasedOrder $order, User $user): void
{
    $order->UVCCDE;
    $order->UVCCDE = 1;
    $order->ordered_quantity;
    $order->OTHER;
    $user->UVCCDE;

    AliasedOrder::query()->where('UVCCDE', 1);
    AliasedOrder::query()->orderBy('STATUS');
    AliasedOrder::query()->whereAlias('ordered_quantity', 1);
    AliasedOrder::query()->where('OTHER', 1);
    AliasedOrder::where('STATUS', 'x');
    User::query()->where('UVCCDE', 1);
}
