<?php

namespace IDL\DB2Driver\Eloquent;

use IDL\DB2Driver\Exceptions\ColumnAliasException;
use Illuminate\Support\Str;

/** Case styles for column aliases, shared by the trait and the PHPStan extension. */
final class AliasCase
{
    public static function normalise(string $alias, string $case): string
    {
        return match ($case) {
            'snake' => Str::snake($alias),
            'camel' => Str::camel($alias),
            'none' => $alias,
            default => throw ColumnAliasException::unknownCase($case),
        };
    }
}
