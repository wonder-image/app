<?php

namespace Wonder\App\Support;

use RuntimeException;
use Wonder\App\Model;
use Wonder\App\ModelRegistry;

/**
 * Registro delle classi di sincronizzazione (`code` / `content` / `environment`)
 * di ogni tabella nota: quelle dei Model (`Model::syncClass()`) più le poche
 * tabelle senza Model, che si dichiarano con {@see register()}.
 *
 * Non fa fallire il boot: `report()` descrive com'è la situazione e
 * `assertComplete()` lancia un'eccezione se ci sono tabelle non classificate o
 * classificate due volte. Lo usano il comando di sincronizzazione e il test del
 * pacchetto (lo stesso test può girare nella CI di un sito o di un modulo).
 */
final class SyncClassification
{
    /**
     * Tabelle del framework senza Model.
     *
     * @var array<string, SyncClass>
     */
    private const FRAMEWORK_TABLES = [
        // Storia degli aggiornamenti (`forge update`): è di quell'ambiente.
        'app_update_runs' => SyncClass::Environment,
    ];

    /** @var array<string, SyncClass> */
    private static array $registered = [];

    /**
     * Dichiara la classe di una tabella senza Model (di un modulo o di un sito).
     * Chiamarla due volte per la stessa tabella con classi diverse, o per una
     * tabella già coperta da un Model, la fa comparire fra i doppioni.
     */
    public static function register(string $table, SyncClass $class): void
    {
        self::$registered[trim($table)] = $class;
    }

    public static function reset(): void
    {
        self::$registered = [];
    }

    /**
     * @param list<class-string<Model>>|null $modelClasses default: tutti i Model scoperti
     *
     * @return array{
     *     classes: array<string, SyncClass>,
     *     unclassified: list<string>,
     *     duplicates: array<string, list<string>>,
     *     inconsistent: list<string>
     * } `classes`: tabella → classe, per le tabelle classificate senza ambiguità.
     *   `unclassified`: tabelle di Model con `syncClass()` null (`Tabella (Classe)`).
     *   `duplicates`: tabelle con due classificazioni in conflitto, con chi le dichiara.
     *   `inconsistent`: tabelle `environment` che però finiscono in `shared/sync-data.json`.
     */
    public static function report(?array $modelClasses = null): array
    {
        $modelClasses ??= ModelRegistry::concreteClasses();

        /** @var array<string, list<array{source: string, class: ?SyncClass, model: ?string}>> $declared */
        $declared = [];

        foreach ($modelClasses as $modelClass) {
            $table = trim((string) ($modelClass::$table ?? ''));

            if ($table === '') {
                continue;
            }

            $declared[$table][] = ['source' => $modelClass, 'class' => $modelClass::syncClass(), 'model' => $modelClass];
        }

        foreach ([...self::FRAMEWORK_TABLES, ...self::$registered] as $table => $class) {
            $declared[$table][] = ['source' => 'register()', 'class' => $class, 'model' => null];
        }

        ksort($declared);

        $classes = [];
        $unclassified = [];
        $duplicates = [];
        $inconsistent = [];

        foreach ($declared as $table => $entries) {
            if (self::conflicting($entries)) {
                $duplicates[$table] = array_map(
                    static fn (array $e): string => $e['source'].' ('.($e['class']?->value ?? 'non classificata').')',
                    $entries,
                );

                continue;
            }

            $class = null;

            foreach ($entries as $entry) {
                $class ??= $entry['class'];
            }

            if ($class === null) {
                $unclassified[] = $table.' ('.$entries[0]['source'].')';

                continue;
            }

            $classes[$table] = $class;

            foreach ($entries as $entry) {
                if ($class === SyncClass::Environment && $entry['model'] !== null && $entry['model']::syncSchema() instanceof SyncSchema) {
                    $inconsistent[$table] = $table;
                }
            }
        }

        return [
            'classes' => $classes,
            'unclassified' => $unclassified,
            'duplicates' => $duplicates,
            'inconsistent' => array_values($inconsistent),
        ];
    }

    /** La classe di una tabella, o null se non è classificata (o è in conflitto). */
    public static function classOf(string $table): ?SyncClass
    {
        return self::report()['classes'][$table] ?? null;
    }

    /**
     * Le tabelle di una classe, in ordine alfabetico.
     *
     * @return list<string>
     */
    public static function tables(SyncClass $class): array
    {
        $tables = array_keys(array_filter(
            self::report()['classes'],
            static fn (SyncClass $c): bool => $c === $class,
        ));

        sort($tables);

        return $tables;
    }

    /**
     * @param list<class-string<Model>>|null $modelClasses
     *
     * @throws RuntimeException se c'è una tabella non classificata, classificata due volte
     *                          o `environment` ma in `shared/sync-data.json`
     */
    public static function assertComplete(?array $modelClasses = null): void
    {
        $report = self::report($modelClasses);
        $problems = [];

        if ($report['unclassified'] !== []) {
            $problems[] = 'Tabelle non classificate (aggiungere Model::syncClass() o SyncClassification::register()): '
                .implode(', ', $report['unclassified']);
        }

        foreach ($report['duplicates'] as $table => $sources) {
            $problems[] = "Tabella {$table} classificata in modo diverso o da più dichiaranti: ".implode('; ', $sources);
        }

        if ($report['inconsistent'] !== []) {
            $problems[] = 'Tabelle environment con syncSchema() (finirebbero in shared/sync-data.json): '
                .implode(', ', $report['inconsistent']);
        }

        if ($problems !== []) {
            throw new RuntimeException(implode("\n", $problems));
        }
    }

    /**
     * Due dichiarazioni sulla stessa tabella sono un conflitto se le classi
     * differiscono (o una manca), o se vengono da Model senza parentela: un
     * Model che estende l'altro (come `ExternalReference` di un modulo sul
     * suo omonimo del framework) è un'estensione, non un doppione.
     *
     * @param list<array{source: string, class: ?SyncClass, model: ?string}> $entries
     */
    private static function conflicting(array $entries): bool
    {
        if (count($entries) < 2) {
            return false;
        }

        $first = $entries[0];

        foreach (array_slice($entries, 1) as $entry) {
            if ($entry['class'] !== $first['class']) {
                // Una sola classe nulla (non ancora classificata) più una vera
                // è un doppione quanto due classi diverse.
                return true;
            }

            if ($entry['model'] === null || $first['model'] === null) {
                return true;
            }

            if (!is_a($entry['model'], $first['model'], true) && !is_a($first['model'], $entry['model'], true)) {
                return true;
            }
        }

        return false;
    }
}
