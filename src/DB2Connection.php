<?php

namespace IDL\DB2Driver;

use Closure;
use IDL\DB2Driver\Exceptions\ReadOnlyModeException;
use IDL\DB2Driver\Schema\DB2Builder;
use IDL\DB2Driver\Schema\DB2SchemaGrammar;
use Illuminate\Database\Connection;
use PDO;

class DB2Connection extends Connection
{
    /**
     * The name of the default schema.
     */
    protected $defaultSchema;

    /**
     * The name of the current schema in use.
     */
    protected $currentSchema;

    public function __construct(
        PDO $pdo,
        string $database = '',
        string $tablePrefix = '',
        array $config = []
        ) {
        parent::__construct($pdo, $database, $tablePrefix, $config);
        $this->currentSchema = $this->defaultSchema = strtoupper($config['schema'] ?? '');
        $this->useDefaultSchemaGrammar();
    }

    /**
     * Whether write statements are blocked ('readonly' connection option).
     */
    public function isReadOnly(): bool
    {
        return (bool) ($this->config['readonly'] ?? false);
    }

    /**
     * Whether UPDATE/MERGE statements are allowed while in read-only mode
     * ('readonly_allow_updates' connection option).
     */
    public function allowsUpdatesInReadOnly(): bool
    {
        return (bool) ($this->config['readonly_allow_updates'] ?? false);
    }

    /**
     * Run a SQL statement, rejecting writes when in read-only mode.
     */
    protected function run($query, $bindings, Closure $callback)
    {
        $this->guardReadOnly($query);

        return parent::run($query, $bindings, $callback);
    }

    /**
     * @throws ReadOnlyModeException
     */
    protected function guardReadOnly(string $query): void
    {
        if (! $this->isReadOnly()) {
            return;
        }

        $operation = $this->classifyWrite($query);

        if ($operation === null) {
            return;
        }

        if ($operation === ReadOnlyModeException::TYPE_UPDATE && $this->allowsUpdatesInReadOnly()) {
            return;
        }

        throw new ReadOnlyModeException($operation, $query, (string) ($this->config['name'] ?? ''));
    }

    /**
     * Determine which kind of write (if any) a SQL statement performs.
     */
    protected function classifyWrite(string $query): ?string
    {
        // Strip leading whitespace, parentheses and SQL comments.
        $sql = preg_replace('/^(?:\s+|\(|--[^\n]*\n?|\/\*.*?\*\/)+/s', '', $query) ?? $query;

        // DB2 data-change-table-reference, e.g. "select id from new table (insert ...)"
        // which the processor uses to fetch generated IDs.
        if (preg_match('/^select\b.*?\bfrom\s+(?:new|old|final)\s+table\s*\(\s*(insert|update|delete|merge)\b/is', $sql, $m)) {
            return $this->classifyWrite($m[1]);
        }

        if (! preg_match('/^([a-z]+)/i', $sql, $m)) {
            return null;
        }

        return match (strtoupper($m[1])) {
            'INSERT' => ReadOnlyModeException::TYPE_INSERT,
            'UPDATE', 'MERGE' => ReadOnlyModeException::TYPE_UPDATE,
            'DELETE' => ReadOnlyModeException::TYPE_DELETE,
            'CREATE', 'ALTER', 'DROP', 'RENAME', 'TRUNCATE' => ReadOnlyModeException::TYPE_SCHEMA,
            default => null,
        };
    }

    /**
     * Get the name of the default schema.
     */
    public function getDefaultSchema(): string
    {
        return $this->defaultSchema;
    }

    /**
     * Reset to default the current schema.
     */
    public function resetCurrentSchema()
    {
        $this->setCurrentSchema($this->getDefaultSchema());
    }

    /**
     * Set the name of the current schema.
     */
    public function setCurrentSchema(string $schema)
    {
        $this->statement('SET SCHEMA ?', [$schema !== "" ? strtoupper($schema) : "DEFAULT"]);
    }

    /**
     * Execute a system command on IBMi.
     */
    public function executeCommand($command)
    {
        $this->statement('CALL QSYS2.QCMDEXC(?)', [$command]);
    }

    /**
     * Get a schema builder instance for the connection.
     */
    public function getSchemaBuilder()
    {
        return new DB2Builder($this);
    }

    /**
     * @return \Illuminate\Database\Grammar
     */
    protected function getDefaultQueryGrammar()
    {
        $defaultGrammar = new DB2QueryGrammar($this);

        // If a date format was specified in constructor
        if (array_key_exists('date_format', $this->config)) {
            $defaultGrammar->setDateFormat($this->config['date_format']);
        }

        // If offset compatability mode was specified in constructor
        if (array_key_exists('offset_compatibility_mode', $this->config)) {
            $defaultGrammar->setOffsetCompatibilityMode($this->config['offset_compatibility_mode']);
        }

        return $defaultGrammar;
    }

    /**
     * Get the efault grammar for specified Schema
     */
    protected function getDefaultSchemaGrammar(): \Illuminate\Database\Grammar
    {
        return new DB2SchemaGrammar($this);
    }

    /**
     * @var DB2Processor
     */
    protected $postProcessor;

    /**
     * Get the query post processor used by the connection.
     */
    public function getPostProcessor(): DB2Processor
    {
        return $this->postProcessor;
    }

    /**
     * Get the default post processor instance
     */
    protected function getDefaultPostProcessor(): \Illuminate\Database\Query\Processors\Processor
    {
        return new DB2Processor;
    }
}
