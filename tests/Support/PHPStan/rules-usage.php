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

function more(Customer $customer): void
{
    AliasedOrder::query()->orWhereIn('STATUS', ['x']);
    AliasedOrder::query()->whereNotBetween('UVCCDE', [1, 2]);
    AliasedOrder::query()->where(['STATUS' => 'x', 'OTHER' => 1]);
    AliasedOrder::query()->select('OTHER', 'UVCCDE');
    AliasedOrder::query()->addSelect(['STATUS']);
    AliasedOrder::query()->max('UVCCDE');
    $customer->orders()->where('STATUS', 'x');
    AliasedOrder::query()->whereAlias('order_status', 'x');
}
