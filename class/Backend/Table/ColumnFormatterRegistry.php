<?php

namespace Wonder\Backend\Table;

/**
 * Registry dei formatter di colonna invocabili via TableColumn::formatter().
 * Il nome viaggia nel POST di list-table (giro DataTables): il registry è la
 * whitelist — un nome non registrato non viene mai invocato. Le callable vivono
 * solo server-side (mai serializzate).
 */
final class ColumnFormatterRegistry
{
    /** @var array<string, callable> */
    private static array $formatters = [];

    public static function register(string $name, callable $formatter): void
    {
        $name = trim($name);

        if ($name !== '') {
            self::$formatters[$name] = $formatter;
        }
    }

    public static function has(string $name): bool
    {
        return isset(self::$formatters[trim($name)]);
    }

    /** Invoca il formatter registrato; '' se il nome non è registrato. */
    public static function call(string $name, array $row): string
    {
        $name = trim($name);

        if (!isset(self::$formatters[$name])) {
            return '';
        }

        return (string) (self::$formatters[$name])($row);
    }

    public static function reset(): void
    {
        self::$formatters = [];
    }
}
