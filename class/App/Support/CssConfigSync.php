<?php

namespace Wonder\App\Support;

/**
 * Facade per la sincronizzazione delle tabelle CSS.
 *
 * Delega tutta la logica a `TableSync`, fornendo un accesso
 * filtrato alle sole tabelle CSS. Per uso generale preferire
 * `TableSync` direttamente.
 *
 * @see TableSync
 */
final class CssConfigSync
{
    /**
     * Path del file di sync. Punta al file unificato gestito da
     * `TableSync` (non piu a un file CSS-specifico).
     */
    public const CONFIG_PATH = TableSync::CONFIG_PATH;

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
     * @deprecated Usa TableSync::setSyncTables() per il filtro globale.
     */
    public static function setSyncTables(?array $tables): void
    {
        TableSync::setSyncTables($tables);
    }

    /**
     * @deprecated Usa TableSync::syncTables() per il filtro globale.
     */
    public static function syncTables(): array
    {
        return array_values(
            array_intersect(self::ALL_TABLES, TableSync::syncTables())
        );
    }

    /**
     * Importa css-config.json o sync-data.json dal root del progetto.
     * Delega a TableSync.
     */
    public static function importIfExists(string $root): bool
    {
        return TableSync::importIfExists($root);
    }

    /**
     * Importa solo le tabelle CSS da un array di configurazione.
     */
    public static function importConfig(array $config): bool
    {
        return TableSync::importConfig($config, self::ALL_TABLES);
    }

    /**
     * Esporta solo le tabelle CSS.
     */
    public static function exportConfig(): array
    {
        return TableSync::exportConfig(self::ALL_TABLES);
    }

    /**
     * Auto-export: delega a TableSync che esporta tutte le tabelle
     * sincronizzabili (non solo CSS) nel file unificato.
     */
    public static function autoExport(): void
    {
        TableSync::autoExport();
    }
}
