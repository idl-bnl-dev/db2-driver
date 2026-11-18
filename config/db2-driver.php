<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Read-only mode
    |--------------------------------------------------------------------------
    |
    | When enabled, every db2 connection rejects inserts, updates, deletes and
    | schema changes (CREATE, ALTER, DROP, ...) by throwing a
    | IDL\DB2Driver\Exceptions\ReadOnlyModeException. These are defaults: a
    | 'readonly' / 'readonly_allow_updates' key on an individual connection in
    | config/database.php takes precedence.
    |
    */

    'readonly' => env('DB2_READONLY', false),

    /*
    |--------------------------------------------------------------------------
    | Allow updates in read-only mode
    |--------------------------------------------------------------------------
    |
    | Permit UPDATE and MERGE statements while read-only mode is enabled.
    | Inserts, deletes and schema changes stay blocked.
    |
    */

    'readonly_allow_updates' => env('DB2_READONLY_ALLOW_UPDATES', false),

    /*
    |--------------------------------------------------------------------------
    | Column alias case
    |--------------------------------------------------------------------------
    |
    | Case style of the readable column aliases declared with the
    | HasColumnAliases trait: 'snake' (ordered_quantity), 'camel'
    | (orderedQuantity) or 'none'. Aliases and the keys used to read them are
    | both normalised to it, so either spelling resolves.
    |
    */

    'alias_case' => env('DB2_ALIAS_CASE', 'snake'),

];
