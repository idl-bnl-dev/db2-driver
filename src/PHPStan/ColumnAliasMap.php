<?php

namespace IDL\DB2Driver\PHPStan;

use IDL\DB2Driver\Eloquent\HasColumnAliases;
use PHPStan\Reflection\ClassReflection;

/** Reads the alias declarations of a HasColumnAliases model from its property defaults. */
final class ColumnAliasMap
{
    /** @return array<string, string> alias => real column, empty for a class without the trait */
    public static function aliasesOf(ClassReflection $classReflection): array
    {
        if (! $classReflection->hasTraitUse(HasColumnAliases::class)) {
            return [];
        }

        return array_map('strval', self::defaultOf($classReflection, 'columnAliases'));
    }

    /** @return array<array-key, mixed> */
    public static function castsOf(ClassReflection $classReflection): array
    {
        return self::defaultOf($classReflection, 'casts');
    }

    /** @return array<array-key, mixed> */
    private static function defaultOf(ClassReflection $classReflection, string $property): array
    {
        $default = $classReflection->getNativeReflection()->getDefaultProperties()[$property] ?? [];

        return is_array($default) ? $default : [];
    }
}
