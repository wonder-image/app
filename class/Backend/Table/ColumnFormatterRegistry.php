<?php

namespace Wonder\Backend\Table;

use Wonder\App\ResourceRegistry;

/**
 * Registry dei formatter di colonna invocabili via TableColumn::formatter().
 * Il nome viaggia nel POST di list-table (giro DataTables): il registry è la
 * whitelist — un nome non registrato non viene mai invocato. Le callable vivono
 * solo server-side (mai serializzate).
 *
 * Oltre alla registrazione esplicita per nome (boot.files), acquisisce lazy le
 * closure inline dichiarate nei tableSchema() delle resource, sotto la chiave
 * derivata `{slug}.{colonna}` (come ColumnFunctionRegistry::fromResources).
 */
final class ColumnFormatterRegistry
{
    /** @var array<string, callable> */
    private static array $formatters = [];

    private static bool $scanned = false;

    public static function register(string $name, callable $formatter): void
    {
        $name = trim($name);

        if ($name !== '') {
            self::$formatters[$name] = $formatter;
        }
    }

    /**
     * Registra le closure inline (->formatter(fn)) di una resource sotto la
     * chiave derivata `{slug}.{colonna}`. Duck-typed: usa solo slug() e
     * tableSchema(). Difensivo su schema malformati.
     */
    public static function registerFromResource(string $resourceClass): void
    {
        try {
            $slug = (string) $resourceClass::slug();
            $columns = $resourceClass::tableSchema();
        } catch (\Throwable) {
            return;
        }

        if ($slug === '' || !is_iterable($columns)) {
            return;
        }

        foreach ($columns as $column) {
            try {
                if (!is_object($column) || !method_exists($column, 'toArray')) {
                    continue;
                }

                $schema = (array) $column->toArray();
                $formatter = $schema['formatter'] ?? null;
                $name = (string) ($schema['name'] ?? '');

                if ($formatter instanceof \Closure && $name !== '') {
                    self::register($slug.'.'.$name, $formatter);
                }
            } catch (\Throwable) {
                continue;
            }
        }
    }

    public static function has(string $name): bool
    {
        self::ensureResources();

        return isset(self::$formatters[trim($name)]);
    }

    /** Invoca il formatter registrato; '' se il nome non è registrato. */
    public static function call(string $name, array $row): string
    {
        self::ensureResources();

        $name = trim($name);

        if (!isset(self::$formatters[$name])) {
            return '';
        }

        return (string) (self::$formatters[$name])($row);
    }

    public static function reset(): void
    {
        self::$formatters = [];
        self::$scanned = false;
    }

    /**
     * Scan lazy per-request: registra le closure inline di tutte le resource.
     * `$scanned` è impostato PRIMA dello scan per evitare ricorsione se una
     * tableSchema() tocca il registry.
     */
    private static function ensureResources(): void
    {
        if (self::$scanned) {
            return;
        }

        self::$scanned = true;

        try {
            $classes = ResourceRegistry::classes();
        } catch (\Throwable) {
            return;
        }

        foreach ($classes as $resourceClass) {
            try {
                self::registerFromResource((string) $resourceClass);
            } catch (\Throwable) {
                continue;
            }
        }
    }
}
