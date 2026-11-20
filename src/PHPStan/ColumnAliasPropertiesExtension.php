<?php

namespace IDL\DB2Driver\PHPStan;

use Carbon\CarbonImmutable;
use IDL\DB2Driver\Eloquent\AliasCase;
use IDL\DB2Driver\Exceptions\ColumnAliasException;
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
use PHPStan\Type\TypeCombinator;
use PHPStan\Type\Type;

/**
 * Declares the aliases of HasColumnAliases models as properties, typed from the real column's
 * cast, nullable because a column can be NULL (mixed without a cast). Reads the `$columnAliases` and `$casts` property defaults, so aliases
 * from an overridden columnAliases() method are not seen.
 */
final class ColumnAliasPropertiesExtension implements PropertiesClassReflectionExtension
{
    /** @var array<string, array<string, string>> normalised alias => real column, per class */
    private array $maps = [];

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
        $map = $this->maps[$classReflection->getName()] ??= $this->mapOf($classReflection);

        return $map[$propertyName] ?? $map[$this->normalise($propertyName)] ?? null;
    }

    /** @return array<string, string> */
    private function mapOf(ClassReflection $classReflection): array
    {
        $map = [];

        foreach (ColumnAliasMap::aliasesOf($classReflection) as $alias => $column) {
            $map[$this->normalise($alias)] = $column;
        }

        return $map;
    }

    /** An unknown db2AliasCase must not crash the analysis: fall back to exact alias names. */
    private function normalise(string $name): string
    {
        try {
            return AliasCase::normalise($name, $this->aliasCase);
        } catch (ColumnAliasException) {
            return $name;
        }
    }

    private function typeFor(ClassReflection $classReflection, string $column): Type
    {
        $cast = ColumnAliasMap::castsOf($classReflection)[$column] ?? null;

        return TypeCombinator::addNull(match (is_string($cast) ? strtolower(explode(':', $cast)[0]) : null) {
            'int', 'integer', 'timestamp' => new IntegerType,
            'float', 'double', 'real' => new FloatType,
            'string', 'decimal' => new StringType,
            'bool', 'boolean' => new BooleanType,
            'array', 'json' => new ArrayType(new MixedType, new MixedType),
            'date', 'datetime' => new ObjectType(Carbon::class),
            'immutable_date', 'immutable_datetime' => new ObjectType(CarbonImmutable::class),
            default => new MixedType,
        });
    }
}
