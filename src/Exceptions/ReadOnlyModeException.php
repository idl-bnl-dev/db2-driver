<?php

namespace IDL\DB2Driver\Exceptions;

use RuntimeException;

class ReadOnlyModeException extends RuntimeException
{
    public const TYPE_INSERT = 'insert';

    public const TYPE_UPDATE = 'update';

    public const TYPE_DELETE = 'delete';

    public const TYPE_SCHEMA = 'schema';

    public function __construct(
        public readonly string $operation,
        public readonly string $query,
        public readonly string $connectionName = '',
    ) {
        parent::__construct(sprintf(
            'Connection%s is in read-only mode; %s statements are not allowed.',
            $connectionName !== '' ? " [{$connectionName}]" : '',
            $operation
        ));
    }
}
