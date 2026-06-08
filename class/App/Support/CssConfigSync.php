<?php

namespace Wonder\App\Support;

/**
 * Importa / esporta la configurazione CSS (7 tabelle) come JSON.
 *
 * Usata sia dal pipeline `build/update/css.php` (importazione automatica
 * di `css-config.json` prima della rigenerazione CSS) sia dai comandi
 * CLI `forge css:export` e `forge css:import`, sia dall'hook automatico
 * post-save delle Resource CSS (`autoExport()`).
 */
final class CssConfigSync
{
    private const SYSTEM_COLUMNS = ['id', 'last_modified', 'creation', 'deleted'];

    private const SINGLETON_TABLES = [
        'css_default',
        'css_input',
        'css_modal',
        'css_dropdown',
        'css_alert',
    ];

    private const MULTI_ROW_TABLES = [
        'css_font',
        'css_color',
    ];

    public const ALL_TABLES = [
        'css_font',
        'css_color',
        'css_default',
        'css_input',
        'css_modal',
        'css_dropdown',
        'css_alert',
    ];

    /**
     * Se `css-config.json` esiste nel root del progetto, importa la
     * configurazione nel DB. Pensato per essere chiamato da
     * `build/update/css.php` durante `forge update`.
     */
    public static function importIfExists(string $root): bool
    {
        $file = rtrim($root, '/').'/css-config.json';

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
     * Importa un array di configurazione (già decodificato dal JSON)
     * nelle 7 tabelle CSS del DB.
     */
    public static function importConfig(array $config): bool
    {
        $imported = 0;

        foreach (self::SINGLETON_TABLES as $table) {
            if (!isset($config[$table]) || !is_array($config[$table]) || count($config[$table]) === 0) {
                continue;
            }

            $values = self::cleanRow($config[$table][0]);

            if ($values === []) {
                continue;
            }

            if (sqlSelect($table, ['id' => 1], 1)->exists) {
                sqlModify($table, $values, 'id', '1');
            } else {
                sqlInsert($table, $values);
            }

            $imported++;
        }

        foreach (self::MULTI_ROW_TABLES as $table) {
            if (!isset($config[$table]) || !is_array($config[$table])) {
                continue;
            }

            sqlTruncate($table);

            foreach ($config[$table] as $row) {
                $values = self::cleanRow($row);

                if ($values !== []) {
                    sqlInsert($table, $values);
                }
            }

            $imported++;
        }

        return $imported > 0;
    }

    /**
     * Esporta tutte le 7 tabelle CSS come array associativo pronto
     * per `json_encode()`.
     */
    public static function exportConfig(): array
    {
        $config = [];

        foreach (self::ALL_TABLES as $table) {
            $result = sqlSelect($table);
            $rows = [];

            foreach ($result->row as $row) {
                $rows[] = self::cleanRow($row);
            }

            $config[$table] = $rows;
        }

        return $config;
    }

    /**
     * Esporta automaticamente `css-config.json` nel root del progetto,
     * se la variabile d'ambiente `CSS_AUTO_EXPORT` è impostata a `true`.
     *
     * Pensata per essere chiamata dagli hook `afterUpdate` / `afterStore`
     * / `afterDelete` delle Resource CSS, in modo che ogni modifica dal
     * backend rigeneri il file JSON committabile in git.
     *
     * Non-blocking: se l'export fallisce (funzioni SQL non disponibili,
     * root non determinabile, permessi file), l'operazione viene
     * silenziosamente ignorata — il salvataggio CSS dal backend non
     * deve mai fallire per colpa dell'export.
     */
    public static function autoExport(): void
    {
        try {
            if (!filter_var($_ENV['CSS_AUTO_EXPORT'] ?? 'false', FILTER_VALIDATE_BOOLEAN)) {
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

            $file = rtrim($root, '/').'/css-config.json';

            // Scrivi solo se il contenuto è effettivamente cambiato,
            // per evitare scritture inutili su disco e diff git spuri.
            $current = file_exists($file) ? file_get_contents($file) : '';
            $newContent = $json."\n";

            if ($current !== $newContent) {
                file_put_contents($file, $newContent);
            }
        } catch (\Throwable) {
            // Non-blocking: il salvataggio CSS non deve mai fallire
            // per colpa dell'auto-export.
        }
    }

    private static function cleanRow(array $row): array
    {
        $cleaned = [];

        foreach ($row as $column => $value) {
            if (in_array($column, self::SYSTEM_COLUMNS, true)) {
                continue;
            }

            $cleaned[$column] = $value;
        }

        return $cleaned;
    }
}
