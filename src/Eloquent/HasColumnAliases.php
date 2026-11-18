<?php

namespace IDL\DB2Driver\Eloquent;

use IDL\DB2Driver\Exceptions\ColumnAliasException;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

/**
 * Readable names for cryptic DB2 columns, opt-in per model.
 *
 * Declare `protected array $columnAliases = ['ordered_quantity' => 'UVCCDE']` (alias => real column).
 * Attributes, fill() and the whereAlias/orderByAlias/selectAliased scopes accept the alias; SQL
 * always uses the real column. Set `$exposeAliases = true` to return aliases from toArray().
 *
 * @property array<string, string> $columnAliases
 */
trait HasColumnAliases
{
    /** @var array<string, array<string, string>> normalised alias => real column, per class and case style */
    protected static array $resolvedColumnAliases = [];

    public function initializeHasColumnAliases(): void
    {
        $this->columnAliasMap();
    }

    public static function flushColumnAliasCache(): void
    {
        static::$resolvedColumnAliases = [];
    }

    /**
     * Alias => real column as declared; override for a computed or file-based source.
     *
     * @return array<string, string>
     */
    public function columnAliases(): array
    {
        return $this->columnAliases ?? [];
    }

    /** The real column behind an alias, or the key itself when it is no alias. */
    public function resolveColumnAlias(string $key): string
    {
        $map = $this->columnAliasMap();

        return $map[$key] ?? $map[$this->normaliseColumnAlias($key)] ?? $key;
    }

    public function getAttribute($key)
    {
        if (is_string($key)) {
            $real = $this->resolveColumnAlias($key);

            // A row selected AS the alias (selectAliased) stores the value under the alias itself.
            $key = $real !== $key && array_key_exists($key, $this->attributes) ? $key : $real;
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        return parent::setAttribute(is_string($key) ? $this->resolveColumnAlias($key) : $key, $value);
    }

    public function fill(array $attributes)
    {
        $translated = [];

        foreach ($attributes as $key => $value) {
            $translated[is_string($key) ? $this->resolveColumnAlias($key) : $key] = $value;
        }

        return parent::fill($translated);
    }

    public function attributesToArray()
    {
        $attributes = parent::attributesToArray();

        if (! ($this->exposeAliases ?? false)) {
            return $attributes;
        }

        $aliasByColumn = array_flip($this->columnAliases());
        $exposed = [];

        foreach ($attributes as $key => $value) {
            $exposed[$aliasByColumn[$key] ?? $key] = $value;
        }

        return $exposed;
    }

    /** @param Builder<static> $query */
    public function scopeWhereAlias(Builder $query, string $alias, mixed ...$arguments): void
    {
        $query->where($this->resolveColumnAlias($alias), ...$arguments);
    }

    /** @param Builder<static> $query */
    public function scopeOrderByAlias(Builder $query, string $alias, string $direction = 'asc'): void
    {
        $query->orderBy($this->resolveColumnAlias($alias), $direction);
    }

    /**
     * Select `REAL as alias`; without arguments every declared alias.
     *
     * @param  Builder<static>  $query
     * @param  list<string>  $aliases
     */
    public function scopeSelectAliased(Builder $query, array $aliases = []): void
    {
        $declared = $this->columnAliases();
        $aliases = $aliases === [] ? array_keys($declared) : $aliases;

        foreach ($aliases as $alias) {
            $query->addSelect($this->resolveColumnAlias($alias).' as '.$alias);
        }
    }

    /** @return array<string, string> */
    protected function columnAliasMap(): array
    {
        $style = (string) config('db2-driver.alias_case', 'snake');
        $cacheKey = static::class.'|'.$style;

        return static::$resolvedColumnAliases[$cacheKey] ??= $this->buildColumnAliasMap();
    }

    /** @return array<string, string> */
    protected function buildColumnAliasMap(): array
    {
        $declared = $this->columnAliases();
        $columns = array_flip(array_values($declared)) + $this->getCasts();
        $map = [];

        foreach ($declared as $alias => $column) {
            $normalised = $this->normaliseColumnAlias($alias);

            $reason = match (true) {
                isset($map[$normalised]) => 'is declared twice',
                isset($columns[$normalised]) => 'shadows a real column or cast',
                $this->hasGetMutator($normalised) || $this->hasAttributeGetMutator($normalised) => 'shadows an accessor',
                default => null,
            };

            if ($reason !== null) {
                throw ColumnAliasException::collision(static::class, $alias, $reason);
            }

            $map[$normalised] = $column;
        }

        return $map;
    }

    protected function normaliseColumnAlias(string $alias): string
    {
        $case = (string) config('db2-driver.alias_case', 'snake');

        return match ($case) {
            'snake' => Str::snake($alias),
            'camel' => Str::camel($alias),
            'none' => $alias,
            default => throw ColumnAliasException::unknownCase($case),
        };
    }
}
