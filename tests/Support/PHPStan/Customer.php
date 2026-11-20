<?php

namespace IDL\DB2Driver\Tests\Support\PHPStan;

use IDL\DB2Driver\Tests\Support\Models\AliasedOrder;

class Customer extends \Illuminate\Database\Eloquent\Model
{
    /** @return \Illuminate\Database\Eloquent\Relations\HasMany<AliasedOrder, $this> */
    public function orders(): \Illuminate\Database\Eloquent\Relations\HasMany
    {
        return $this->hasMany(AliasedOrder::class);
    }
}
