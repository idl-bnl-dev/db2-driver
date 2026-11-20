<?php

namespace IDL\DB2Driver\Eloquent;

use IDL\DB2Driver\Exceptions\ColumnAliasException;
use Illuminate\Database\Eloquent\Builder;

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
    /** @var array<class-string, array<string, string>> normalised alias => real column, per class */
    protected static array $resolvedColumnAliases = [];

    /** @var array<class-string, string> alias_case read once per class; flushColumnAliasCache() re-reads it */
    protected static array $columnAliasStyles = [];

    public function initializeHasColumnAliases(): void
    {
        $this->columnAliasMap();
    }

    public static function flushColumnAliasCache(): void
    {
        static::$resolvedColumnAliases = [];
        static::$columnAliasStyles = [];
    }

    /**
     * Alias => real column as declared; override for a computed or file-based source.
     *
     * @return array<string, string>
     */
    public function columnAliases(): array
    {
        // property_exists: reading an undeclared property would go through Model::__isset and recurse into getAttribute().
        return property_exists($this, 'columnAliases') ? $this->columnAliases : [];
    }

    /** The real column behind an alias, or the key itself when it is no alias. */
    public function resolveColumnAlias(string $key): string
    {
        $map = $this->columnAliasMap();

        return $map[$key] ?? $map[$this->normaliseColumnAlias($key)] ?? $key;
    }

    /** The real column behind an alias; unlike resolveColumnAlias(), an unknown alias is an error. */
    public function requireColumnAlias(string $alias): string
    {
        $map = $this->columnAliasMap();

        return $map[$alias] ?? $map[$this->normaliseColumnAlias($alias)]
            ?? throw ColumnAliasException::unknownAlias(static::class, $alias);
    }

    public function getAttribute($key)
    {
        if (is_string($key)) {
            $real = $this->resolveColumnAlias($key);

            if ($real !== $key) {
                $selected = $this->selectedAliasKey($key, $real);

                // A row selected AS the alias (selectAliased) holds the raw value under the alias itself;
                // cast and accessor of the real column still apply to it.
                return $selected !== null
                    ? $this->transformModelValue($real, $this->attributes[$selected])
                    : parent::getAttribute($real);
            }
        }

        return parent::getAttribute($key);
    }

    public function setAttribute($key, $value)
    {
        if (is_string($key)) {
            $real = $this->resolveColumnAlias($key);

            if ($real !== $key) {
                // Drop a value selected under the alias, or the write would be shadowed on the next read.
                $selected = $this->selectedAliasKey($key, $real);

                if ($selected !== null) {
                    unset($this->attributes[$selected]);
                }

                $key = $real;
            }
        }

        return parent::setAttribute($key, $value);
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

        if (! (property_exists($this, 'exposeAliases') && $this->exposeAliases)) {
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
        $query->where($this->requireColumnAlias($alias), ...$arguments);
    }

    /** @param Builder<static> $query */
    public function scopeOrderByAlias(Builder $query, string $alias, string $direction = 'asc'): void
    {
        $query->orderBy($this->requireColumnAlias($alias), $direction);
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
            // The alias is emitted unquoted, so it must be a plain identifier.
            if (! is_string($alias) || ! preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $alias)) {
                throw ColumnAliasException::invalidSelectAlias((string) (is_scalar($alias) ? $alias : gettype($alias)));
            }

            $query->addSelect($this->requireColumnAlias($alias).' as '.$alias);
        }
    }

    /**
     * The attribute key a selectAliased() row stores an alias under: as written, or case-folded
     * because Db2 upper-cases unquoted labels. Null when the row has the real column or no such label.
     */
    private function selectedAliasKey(string $alias, string $real): ?string
    {
        if (array_key_exists($alias, $this->attributes)) {
            return $alias;
        }

        if (array_key_exists($real, $this->attributes)) {
            return null;
        }

        foreach ([strtoupper($alias), strtolower($alias)] as $folded) {
            if ($folded !== $real && array_key_exists($folded, $this->attributes)) {
                return $folded;
            }
        }

        return null;
    }

    /** @return array<string, string> */
    protected function columnAliasMap(): array
    {
        return static::$resolvedColumnAliases[static::class] ??= $this->buildColumnAliasMap();
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
        $style = static::$columnAliasStyles[static::class] ??= (string) config('db2-driver.alias_case', 'snake');

        return AliasCase::normalise($alias, $style);
    }
}
