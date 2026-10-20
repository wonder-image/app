<?php

namespace Wonder\Backend\Table;

use Wonder\App\Table as AppTable;

/**
 * Validazione della richiesta POST di `api.backend.list.table`.
 *
 * La tabella e lo schema arrivano dal client: valgono solo se presenti in
 * `Wonder\App\Table::$list`, il registro popolato al bootstrap da
 * `app/config/app/table.php` (tabelle legacy di `app/build/table` e
 * `custom/build/table`, Model di `ModelRegistry`, schemi delle Resource).
 */
final class ListTableRequest
{
    /**
     * Chiave canonica (minuscola, come in `Table::$list`) della tabella
     * richiesta se registrata, altrimenti null (l'handler risponde 422).
     *
     * @param array<string,mixed>|null $registry default `Table::$list`
     */
    public static function table(mixed $table, ?array $registry = null): ?string
    {
        if (!is_string($table) || $table === '') {
            return null;
        }

        return self::registered($table, $registry) ? strtolower($table) : null;
    }

    /**
     * Schema richiesto se registrato; uno schema sconosciuto viene ignorato.
     *
     * @param array<string,mixed>|null $registry default `Table::$list`
     */
    public static function schema(mixed $schema, ?array $registry = null): string
    {
        if (!is_string($schema) || $schema === '') {
            return '';
        }

        return self::registered($schema, $registry) ? $schema : '';
    }

    /** @param array<string,mixed>|null $registry */
    private static function registered(string $name, ?array $registry): bool
    {
        return array_key_exists(strtolower($name), $registry ?? AppTable::$list);
    }
}
