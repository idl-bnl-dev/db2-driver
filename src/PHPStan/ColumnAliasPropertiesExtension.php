<?php

namespace IDL\DB2Driver\PHPStan;

use IDL\DB2Driver\Eloquent\AliasCase;
use IDL\DB2Driver\Eloquent\HasColumnAliases;
use Illuminate\Support\Carbon;
use PHPStan\Reflection\ClassReflection;
use PHPStan\Reflection\PropertiesClassReflectionExtension;
use PHPStan\Reflection\PropertyReflection;
use PHPStan\Type\ArrayType;
use PHPStan\Type\BooleanType;
use PHPStan\Type\FloatType;
use PHPStan\Type\IntegerType;
use PHPStan\Type\MixedType;
use PHPStan\Type\ObjectType;
use PHPStan\Type\StringType;
use PHPStan\Type\Type;

/**
 * Declares the aliases of HasColumnAliases models as properties, typed from the real column's
 * cast (mixed without one). Reads the `$columnAliases` and `$casts` property defaults, so aliases
 * from an overridden columnAliases() method are not seen.
 */
final class ColumnAliasPropertiesExtension implements PropertiesClassReflectionExtension
{
    public function __construct(private string $aliasCase = 'snake') {}

    public function hasProperty(ClassReflection $classReflection, string $propertyName): bool
    {
        return $this->columnFor($classReflection, $propertyName) !== null;
    }

    public function getProperty(ClassReflection $classReflection, string $propertyName): PropertyReflection
    {
        $column = (string) $this->columnFor($classReflection, $propertyName);

        return new ColumnAliasPropertyReflection($classReflection, $this->typeFor($classReflection, $column));
    }

    private function columnFor(ClassReflection $classReflection, string $propertyName): ?string
    {
        if (! $classReflection->hasTraitUse(HasColumnAliases::class)) {
            return null;
        }

        $map = [];

        foreach ($this->defaultOf($classReflection, 'columnAliases') as $alias => $column) {
            $map[AliasCase::normalise((string) $alias, $this->aliasCase)] = (string) $column;
        }

        return $map[$propertyName] ?? $map[AliasCase::normalise($propertyName, $this->aliasCase)] ?? null;
    }

    private function typeFor(ClassReflection $classReflection, string $column): Type
    {
        $cast = $this->defaultOf($classReflection, 'casts')[$column] ?? null;

        return match (is_string($cast) ? strtolower(explode(':', $cast)[0]) : null) {
            'int', 'integer' => new IntegerType,
            'float', 'double', 'real' => new FloatType,
            'string', 'decimal' => new StringType,
            'bool', 'boolean' => new BooleanType,
            'array', 'json' => new ArrayType(new MixedType, new MixedType),
            'date', 'datetime', 'immutable_date', 'immutable_datetime', 'timestamp' => new ObjectType(Carbon::class),
            default => new MixedType,
        };
    }

    /** @return array<array-key, mixed> */
    private function defaultOf(ClassReflection $classReflection, string $property): array
    {
        $default = $classReflection->getNativeReflection()->getDefaultProperties()[$property] ?? [];

        return is_array($default) ? $default : [];
    }
}
