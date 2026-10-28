<?php

namespace Wonder\App\Support;

use Wonder\App\Environment;
use Wonder\App\ModelRegistry;
use Wonder\App\ResourceRegistry;
use Wonder\Backend\Support\ReadonlyFields;

/**
 * Sola lettura ed export per nome di tabella, per gli endpoint API
 * generici del backend (`api/backend/*`) che ricevono `table`.
 */
final class SyncedTables
{
    public static function isReadonly(string $table): bool
    {
        $resourceClass = ResourceRegistry::resolveByTable($table);

        if ($resourceClass !== null) {
            return $resourceClass::isReadonly();
        }

        $schema = self::schema($table);

        return $schema instanceof SyncSchema && $schema->localOnly && !Environment::isLocal();
    }

    /**
     * Come {@see isReadonly()}, ma una colonna fra i campi modificabili della
     * Resource ({@see \Wonder\App\Resource::editableWhenReadonly()}) resta
     * scrivibile: es. la foto di una scheda che per il resto arriva dal deploy.
     */
    public static function isReadonlyColumn(string $table, string $column): bool
    {
        if (!self::isReadonly($table)) {
            return false;
        }

        $resourceClass = ResourceRegistry::resolveByTable($table);

        return $resourceClass === null
            || !in_array(trim($column), ReadonlyFields::normalize($resourceClass::editableWhenReadonly()), true);
    }

    public static function exportIfSynced(string $table): void
    {
        if (self::schema($table) instanceof SyncSchema) {
            TableSync::autoExport();
        }
    }

    private static function schema(string $table): ?SyncSchema
    {
        $modelClass = ModelRegistry::all()[$table] ?? null;

        return is_string($modelClass) ? $modelClass::syncSchema() : null;
    }
}
