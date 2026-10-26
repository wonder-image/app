<?php

namespace Wonder\Backend\Table;

use Wonder\App\Table as AppTable;

/**
 * Validazione della richiesta POST di `api.backend.list.table`.
 *
 * La tabella e lo schema arrivano dal client: valgono solo se presenti in
 * `Wonder\App\Table` (`Table::has()`), il registro popolato al bootstrap da
 * `app/config/app/table.php` (tabelle legacy di `app/build/table` e
 * `custom/build/table`, Model di `ModelRegistry`, schemi lazy delle Resource).
 */
final class ListTableRequest
{
    /**
     * Chiave canonica (minuscola, come in `Table`) della tabella
     * richiesta se registrata, altrimenti null (l'handler risponde 422).
     *
     * @param array<string,mixed>|null $registry default il registro di `Table`
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
     * @param array<string,mixed>|null $registry default il registro di `Table`
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
        // Senza registro esplicito: Table::has() riconosce le chiavi lazy senza risolverle.
        return $registry === null
            ? AppTable::has($name)
            : array_key_exists(strtolower($name), $registry);
    }
}
