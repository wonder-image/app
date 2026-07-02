<?php

namespace Wonder\Backend\Table;

use Wonder\App\ResourceRegistry;

/**
 * Whitelist delle funzioni invocabili dalle colonne tabella via
 * ->function(). I nomi arrivano dal POST dell'endpoint list-table: senza
 * whitelist un client autenticato può far eseguire funzioni PHP arbitrarie.
 *
 * La whitelist è derivata server-side: funzioni dichiarate nei tableSchema()
 * delle Resource registrate + default del framework (pagine legacy) +
 * estensioni esplicite via allow() per siti/moduli con pagine legacy custom.
 *
 * active/visible/evidence/empty/permissions* NON passano da qui: sono
 * gestite come special-case interne di Field.
 */
final class ColumnFunctionRegistry
{
    private const FRAMEWORK_DEFAULTS = [
        'mailService',
        'mailLogStatus',
        'consentEventAction',
        'consentEventSource',
    ];

    /** @var array<int,string> */
    private static array $extra = [];

    /** @var array<int,string>|null cache per-request */
    private static ?array $resolved = null;

    public static function allow(string ...$names): void
    {
        foreach ($names as $name) {
            $name = trim($name);

            if ($name !== '' && !in_array($name, self::$extra, true)) {
                self::$extra[] = $name;
            }
        }

        self::$resolved = null;
    }

    public static function isAllowed(string $name): bool
    {
        $name = trim($name);

        return $name !== '' && in_array($name, self::allowed(), true);
    }

    /** @return array<int,string> */
    public static function allowed(): array
    {
        if (self::$resolved !== null) {
            return self::$resolved;
        }

        return self::$resolved = array_values(array_unique(array_merge(
            self::FRAMEWORK_DEFAULTS,
            self::$extra,
            self::fromResources()
        )));
    }

    public static function reset(): void
    {
        self::$extra = [];
        self::$resolved = null;
    }

    /** @return array<int,string> */
    private static function fromResources(): array
    {
        $names = [];

        try {
            $classes = ResourceRegistry::classes();
        } catch (\Throwable) {
            return $names;
        }

        foreach ($classes as $resourceClass) {
            try {
                foreach ($resourceClass::tableSchema() as $column) {
                    if (!is_object($column) || !method_exists($column, 'toArray')) {
                        continue;
                    }

                    $schema = (array) $column->toArray();
                    $functionName = $schema['function']['name'] ?? null;

                    if (is_string($functionName) && trim($functionName) !== '') {
                        $names[] = trim($functionName);
                    }
                }
            } catch (\Throwable) {
                continue;
            }
        }

        return $names;
    }
}
