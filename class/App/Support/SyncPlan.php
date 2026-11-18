<?php

namespace Wonder\App\Support;

use Wonder\App\Environment;

/**
 * Prova a secco della sincronizzazione produzione → staging.
 *
 * Non apre connessioni, non legge né scrive righe: dice solo quali tabelle
 * verrebbero copiate, quali no e perché, e se la classificazione è completa.
 * La copia vera non esiste ancora (punto 6, PR successive).
 */
final class SyncPlan
{
    /**
     * Motivo per cui il comando non deve partire, o null se si può andare avanti.
     * Controllo fatto per primo, prima di leggere qualunque altra cosa.
     */
    public static function refusal(): ?string
    {
        if (!Environment::isStaging()) {
            return 'La sincronizzazione da produzione si esegue solo con APP_ENV=staging.';
        }

        return null;
    }

    /**
     * @return array{
     *   copy: list<string>,
     *   skipCode: list<string>,
     *   skipEnvironment: list<string>,
     *   blocking: list<string>
     * }
     */
    public static function build(): array
    {
        $report = SyncClassification::report();

        $blocking = array_merge(
            $report['unclassified'],
            array_map(
                static fn (string $table, array $by): string => $table.' dichiarata più volte ('.implode(', ', $by).')',
                array_keys($report['duplicates']),
                array_values($report['duplicates'])
            ),
            array_map(
                static fn (string $table): string => $table.' è environment ma ha syncSchema()',
                $report['inconsistent']
            ),
        );

        return [
            'copy' => self::names(SyncClass::Content, $report['classes']),
            'skipCode' => self::names(SyncClass::Code, $report['classes']),
            'skipEnvironment' => self::names(SyncClass::Environment, $report['classes']),
            'blocking' => $blocking,
        ];
    }

    /** @param array<string, SyncClass> $classes */
    private static function names(SyncClass $class, array $classes): array
    {
        $names = array_keys(array_filter($classes, static fn (SyncClass $c): bool => $c === $class));
        sort($names);

        return $names;
    }
}
