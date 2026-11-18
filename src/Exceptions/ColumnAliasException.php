<?php

namespace IDL\DB2Driver\Exceptions;

use InvalidArgumentException;

class ColumnAliasException extends InvalidArgumentException
{
    public static function collision(string $model, string $alias, string $reason): self
    {
        return new self("Column alias [{$alias}] on [{$model}] {$reason}.");
    }

    public static function unknownCase(string $case): self
    {
        return new self("Unknown db2-driver.alias_case [{$case}]; use snake, camel or none.");
    }
}
