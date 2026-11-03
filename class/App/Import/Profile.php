<?php

namespace Wonder\App\Import;

use Throwable;
use Wonder\App\Models\Import\ImportLayout;
use Wonder\App\Models\Import\ImportLayoutColumn;

/**
 * Le colonne scelte dall'amministratore in «Tracciati file»: quali, in che
 * ordine e con che intestazione. Senza un tracciato salvato valgono quelle
 * del codice.
 */
final class Profile
{
    /**
     * Le colonne del tracciato salvato (`$importLayoutId`, o il predefinito),
     * con le sue intestazioni; null quando non ce n'è uno.
     *
     * @return list<Column>|null
     */
    public static function for(Layout $layout, ?int $importLayoutId = null): ?array
    {
        $id = $importLayoutId ?? self::defaultId($layout::key());

        if ($id <= 0) {
            return null;
        }

        $byKey = [];

        foreach ($layout->columns() as $column) {
            $byKey[$column->key] = $column;
        }

        $chosen = [];

        foreach (self::rows($id) as $row) {
            $key = (string) ($row['column_key'] ?? '');

            if (isset($byKey[$key]) && !isset($chosen[$key])) {
                $chosen[$key] = $byKey[$key]->withHeader((string) ($row['header'] ?? ''));
            }
        }

        return $chosen === [] ? null : array_values($chosen);
    }

    /**
     * Le intestazioni del tracciato, per chiave: nell'abbinamento vincono
     * sugli altri nomi.
     *
     * @return array<string, string>
     */
    public static function preferred(Layout $layout, ?int $importLayoutId = null): array
    {
        $preferred = [];

        foreach (self::for($layout, $importLayoutId) ?? [] as $column) {
            $preferred[$column->key] = $column->header;
        }

        return $preferred;
    }

    public static function defaultId(string $layoutKey): int
    {
        try {
            $rows = ImportLayout::find(['layout' => $layoutKey, 'is_default' => 'true', 'deleted' => 'false'], 1, 'id', 'DESC');
        } catch (Throwable) {
            return 0;
        }

        $rows = self::list($rows);

        return (int) ($rows[0]['id'] ?? 0);
    }

    /**
     * I tracciati salvati di un tipo, per la scelta nel caricamento.
     *
     * @return array<int, string>
     */
    public static function options(string $layoutKey): array
    {
        $options = [];

        try {
            $rows = self::list(ImportLayout::find(['layout' => $layoutKey, 'deleted' => 'false'], null, 'name', 'ASC'));
        } catch (Throwable) {
            return [];
        }

        foreach ($rows as $row) {
            $options[(int) $row['id']] = (string) ($row['name'] ?? '');
        }

        return $options;
    }

    /** @return list<array<string, mixed>> */
    private static function rows(int $id): array
    {
        try {
            $rows = self::list(ImportLayoutColumn::find(['import_layout_id' => $id, 'deleted' => 'false']));
        } catch (Throwable) {
            return [];
        }

        usort($rows, static fn (array $a, array $b): int => [(int) ($a['position'] ?? 0), (int) $a['id']] <=> [(int) ($b['position'] ?? 0), (int) $b['id']]);

        return $rows;
    }

    /** @return list<array<string, mixed>> */
    private static function list(mixed $rows): array
    {
        if (!is_array($rows) || $rows === []) {
            return [];
        }

        return isset($rows['id']) ? [$rows] : array_values(array_filter($rows, 'is_array'));
    }
}
