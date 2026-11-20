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

    public static function unknownAlias(string $model, string $alias): self
    {
        return new self("Unknown column alias [{$alias}] on [{$model}].");
    }

    public static function invalidSelectAlias(string $alias): self
    {
        return new self("Cannot select [{$alias}] as an alias: use letters, digits and underscores only, starting with a letter or underscore.");
    }
}
