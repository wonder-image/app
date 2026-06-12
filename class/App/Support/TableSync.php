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
                $result = sqlSelect($table);
                $rows = [];

                foreach ($result->row as $row) {
                    $rows[] = self::cleanRow($row, $schema->excludeColumns);
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

        $imported = 0;
        $db = $GLOBALS['mysqli'] ?? null;

        if ($db instanceof \mysqli) {
            $db->query('SET FOREIGN_KEY_CHECKS = 0');
        }

        try {
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
        } finally {
            if ($db instanceof \mysqli) {
                $db->query('SET FOREIGN_KEY_CHECKS = 1');
            }
        }

        return $imported > 0;
    }

    /**
     * Se il file di sync esiste nel root del progetto, importa la
     * configurazione nel DB.
     *
     * Pensato per essere chiamato da `build/update/css.php` e simili
     * durante `forge update`.
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

            if ($root === '' || !is_dir($root)) {
                return;
            }

            $config = self::exportConfig();
            $json = json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);

            if ($json === false) {
                return;
            }

            $file = rtrim($root, '/').'/'.self::CONFIG_PATH;
            $dir = dirname($file);

            if (!is_dir($dir)) {
                @mkdir($dir, 0777, true);
            }

            $current = file_exists($file) ? file_get_contents($file) : '';
            $newContent = $json."\n";

            if ($current !== $newContent) {
                file_put_contents($file, $newContent);
            }
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
     */
    private static function cleanRow(array $row, array $extraExclude = []): array
    {
        $exclude = array_merge(self::SYSTEM_COLUMNS, $extraExclude);
        $cleaned = [];

        foreach ($row as $column => $value) {
            if (in_array($column, $exclude, true)) {
                continue;
            }

            $cleaned[$column] = $value;
        }

        return $cleaned;
    }
}
