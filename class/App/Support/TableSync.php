<?php

namespace Wonder\App\Support;

use Wonder\App\ModelRegistry;

/**
 * Motore di sincronizzazione tabelle tra ambienti.
 *
 * Scopre automaticamente i Model che dichiarano `syncSchema()` e
 * permette di esportarli / importarli come un unico file JSON
 * (`shared/sync-data.json`) committabile in git.
 *
 * Sostituisce e generalizza `CssConfigSync`: qualunque tabella con
 * `syncSchema()` non-null viene inclusa nel sync.
 *
 * Usata dai comandi `forge export` / `forge import`, dal pipeline
 * `forge update` (import automatico) e dagli hook `autoExport()`
 * delle Resource.
 */
final class TableSync
{
    /**
     * Path relativo al root del progetto del file di sync.
     * Single source of truth per la posizione del file.
     */
    public const CONFIG_PATH = 'shared/sync-data.json';

    /** Colonne di sistema escluse da ogni export/import. */
    private const SYSTEM_COLUMNS = ['id', 'last_modified', 'creation', 'deleted'];

    /** @var string[]|null Override impostato via setSyncTables(). */
    private static ?array $syncTablesOverride = null;

    /** @var array<string, SyncSchema>|null Cache delle tabelle scoperte. */
    private static ?array $discoveredCache = null;

    // ------------------------------------------------------------------
    //  Discovery
    // ------------------------------------------------------------------

    /**
     * Scopre tutte le tabelle sincronizzabili registrate in ModelRegistry.
     *
     * Restituisce un array `['nome_tabella' => SyncSchema, ...]` per
     * ogni Model il cui `syncSchema()` restituisce un valore non-null.
     *
     * Il risultato è cachato per la durata della richiesta. Chiamare
     * `resetCache()` per forzare una nuova scansione.
     *
     * @return array<string, SyncSchema>
     */
    public static function discoverTables(): array
    {
        if (self::$discoveredCache !== null) {
            return self::$discoveredCache;
        }

        $tables = [];

        foreach (ModelRegistry::all() as $tableName => $modelClass) {
            $schema = $modelClass::syncSchema();

            if ($schema instanceof SyncSchema) {
                $tables[$tableName] = $schema;
            }
        }

        self::$discoveredCache = $tables;

        return $tables;
    }

    /**
     * Resetta la cache di discovery. Utile nei test.
     */
    public static function resetCache(): void
    {
        self::$discoveredCache = null;
    }

    // ------------------------------------------------------------------
    //  Configurazione tabelle attive
    // ------------------------------------------------------------------

    /**
     * Imposta le tabelle da includere nel sync (export/import).
     *
     * Chiamare dal bootstrap del sito o da un file di configurazione
     * custom per limitare quali tabelle vengono sincronizzate.
     * Accetta solo nomi presenti tra le tabelle scoperte.
     *
     * Passare `null` per tornare al default (tutte le tabelle scoperte).
     *
     * @param string[]|null $tables
     */
    public static function setSyncTables(?array $tables): void
    {
        if ($tables === null) {
            self::$syncTablesOverride = null;
            return;
        }

        $discovered = array_keys(self::discoverTables());

        self::$syncTablesOverride = array_values(
            array_intersect($tables, $discovered)
        );
    }

    /**
     * Restituisce i nomi delle tabelle attive per il sync.
     *
     * Ordine di precedenza:
     * 1. Override via `setSyncTables()` (piu specifico)
     * 2. Env `SYNC_TABLES` (comma-separated)
     * 3. Default: tutte le tabelle scoperte
     *
     * @return string[]
     */
    public static function syncTables(): array
    {
        $discovered = array_keys(self::discoverTables());

        if (self::$syncTablesOverride !== null) {
            return self::$syncTablesOverride;
        }

        $env = $_ENV['SYNC_TABLES'] ?? '';

        if ($env !== '') {
            $requested = array_map('trim', explode(',', $env));

            return array_values(
                array_intersect($requested, $discovered)
            );
        }

        return $discovered;
    }

    // ------------------------------------------------------------------
    //  Export
    // ------------------------------------------------------------------

    /**
     * Esporta le tabelle attive per il sync come array associativo
     * pronto per `json_encode()`.
     *
     * @param string[]|null $onlyTables  Se non-null, esporta solo
     *                                   queste tabelle (intersecate con
     *                                   le tabelle scoperte).
     */
    public static function exportConfig(?array $onlyTables = null): array
    {
        $discovered = self::discoverTables();
        $tables = $onlyTables !== null
            ? array_intersect($onlyTables, array_keys($discovered))
            : self::syncTables();

        $config = [];

        foreach ($tables as $table) {
            $schema = $discovered[$table] ?? null;

            if ($schema === null) {
                continue;
            }

            if ($schema->singleton) {
                $result = sqlSelect($table, ['id' => 1], 1);
                $rows = $result->exists
                    ? [self::cleanRow($result->row, $schema->excludeColumns)]
                    : [];
            } else {
                $result = sqlSelect($table, null, null, 'id', 'ASC');
                $rows = [];

                foreach ((array) $result->row as $row) {
                    $rows[] = self::cleanRow($row, $schema->excludeColumns, self::keptColumns($schema));
                }
            }

            $config[$table] = $rows;
        }

        return $config;
    }

    // ------------------------------------------------------------------
    //  Import
    // ------------------------------------------------------------------

    /**
     * Importa un array di configurazione (gia decodificato dal JSON)
     * nelle tabelle del DB.
     *
     * @param string[]|null $onlyTables  Se non-null, importa solo
     *                                   queste tabelle.
     */
    public static function importConfig(array $config, ?array $onlyTables = null): bool
    {
        $discovered = self::discoverTables();
        $tables = $onlyTables !== null
            ? array_intersect($onlyTables, array_keys($discovered))
            : self::syncTables();
        $tables = SyncTableSorter::sort($tables, ModelRegistry::all());

        $imported = 0;

        foreach ($tables as $table) {
            $schema = $discovered[$table] ?? null;

            if ($schema === null) {
                continue;
            }

            if (!isset($config[$table]) || !is_array($config[$table])) {
                continue;
            }

            if ($schema->singleton) {
                if (count($config[$table]) === 0) {
                    continue;
                }

                $values = self::cleanRow($config[$table][0], $schema->excludeColumns);

                if ($values === []) {
                    continue;
                }

                if (sqlSelect($table, ['id' => 1], 1)->exists) {
                    sqlModify($table, $values, 'id', '1');
                } else {
                    sqlInsert($table, $values);
                }
            } elseif ($schema->keepIds) {
                self::importKeepingIds($table, $config[$table], $schema);
            } else {
                sqlTruncate($table);

                foreach ($config[$table] as $row) {
                    $values = self::cleanRow($row, $schema->excludeColumns);

                    if ($values !== []) {
                        sqlInsert($table, $values);
                    }
                }
            }

            $imported++;
        }

        return $imported > 0;
    }

    /**
     * Se il file di sync esiste nel root del progetto, importa la
     * configurazione nel DB.
     *
     * Chiamato da `UpdateRunner` durante `forge update`.
     */
    public static function importIfExists(string $root): bool
    {
        $file = rtrim($root, '/').'/'.self::CONFIG_PATH;

        if (!file_exists($file)) {
            return false;
        }

        $json = file_get_contents($file);

        if ($json === false) {
            return false;
        }

        $config = json_decode($json, true);

        if (!is_array($config)) {
            return false;
        }

        return self::importConfig($config);
    }

    /**
     * Import di una tabella con `keepIds()`: inserisce o aggiorna per `id`,
     * segna `deleted = 'true'` le righe assenti dal file, non elimina nulla.
     */
    private static function importKeepingIds(string $table, array $rows, SyncSchema $schema): void
    {
        $fileRows = [];

        foreach ($rows as $row) {
            if (is_array($row)) {
                $fileRows[] = self::cleanRow($row, $schema->excludeColumns, self::keptColumns($schema));
            }
        }

        $existing = sqlSelect($table, null, null, null, null, 'id');
        $existingIds = array_column((array) ($existing->row ?? []), 'id');
        $plan = SyncImportPlan::make($fileRows, $existingIds);

        foreach ($plan->inserts as $values) {
            sqlInsert($table, $values);
        }

        foreach ($plan->updates as $id => $values) {
            if ($values !== []) {
                sqlModify($table, $values, 'id', $id);
            }
        }

        foreach ($plan->softDeletes as $id) {
            sqlModify($table, ['deleted' => 'true'], 'id', $id);
        }
    }

    /**
     * Scrive `shared/sync-data.json` (o `$file`) con le tabelle attive.
     * Non riscrive il file se il contenuto non cambia.
     */
    public static function exportToFile(string $root, ?string $file = null): bool
    {
        $root = rtrim($root, '/');

        if ($root === '' || !is_dir($root)) {
            return false;
        }

        $path = $file ?? $root.'/'.self::CONFIG_PATH;
        $json = json_encode(self::exportConfig(), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

        if ($json === false) {
            return false;
        }

        $dir = dirname($path);

        if (!is_dir($dir) && !@mkdir($dir, 0777, true) && !is_dir($dir)) {
            return false;
        }

        $content = $json."\n";

        if (file_exists($path) && file_get_contents($path) === $content) {
            return true;
        }

        return file_put_contents($path, $content) !== false;
    }

    // ------------------------------------------------------------------
    //  Auto-export (hook post-save)
    // ------------------------------------------------------------------

    /**
     * Esporta automaticamente `shared/sync-data.json` nel root del
     * progetto, se la variabile d'ambiente `SYNC_AUTO_EXPORT` e
     * impostata a `true`.
     *
     * Non-blocking: se l'export fallisce, l'operazione viene
     * silenziosamente ignorata.
     */
    public static function autoExport(): void
    {
        try {
            if (!filter_var($_ENV['SYNC_AUTO_EXPORT'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
                return;
            }

            if (!function_exists('sqlSelect')) {
                return;
            }

            $root = $GLOBALS['ROOT'] ?? '';

            if (!is_string($root) || $root === '') {
                return;
            }

            self::exportToFile($root);
        } catch (\Throwable) {
            // Non-blocking: il salvataggio non deve mai fallire
            // per colpa dell'auto-export.
        }
    }

    // ------------------------------------------------------------------
    //  Helpers
    // ------------------------------------------------------------------

    /**
     * Rimuove le colonne di sistema e quelle escluse dallo schema.
     *
     * @param string[] $extraExclude Colonne aggiuntive da escludere.
     * @param string[] $keep Colonne di sistema da mantenere (es. `id`, `deleted`).
     */
    private static function cleanRow(array $row, array $extraExclude = [], array $keep = []): array
    {
        $exclude = array_diff(array_merge(self::SYSTEM_COLUMNS, $extraExclude), $keep);
        $cleaned = [];

        foreach ($row as $column => $value) {
            if (in_array($column, $exclude, true)) {
                continue;
            }

            $cleaned[$column] = $value;
        }

        return $cleaned;
    }

    /**
     * Colonne di sistema mantenute per lo schema (`id` e `deleted` con `keepIds()`).
     *
     * @return string[]
     */
    private static function keptColumns(SyncSchema $schema): array
    {
        return $schema->keepIds && !$schema->singleton ? ['id', 'deleted'] : [];
    }
}
