<?php

namespace Wonder\App\Support;

use Wonder\App\Model;

final class SyncTableSorter
{
    /**
     * Ordina le tabelle in modo che quelle referenziate da foreign key
     * precedano le tabelle dipendenti. L'ordine originale resta stabile
     * tra tabelle indipendenti e come fallback in caso di cicli.
     *
     * @param string[] $tables
     * @param array<string, class-string<Model>> $models
     * @return string[]
     */
    public static function sort(array $tables, array $models): array
    {
        $tables = array_values(array_unique(array_filter(
            $tables,
            static fn (mixed $table): bool => is_string($table) && trim($table) !== ''
        )));

        $active = array_fill_keys($tables, true);
        $dependencies = [];

        foreach ($tables as $table) {
            $dependencies[$table] = self::dependencies($table, $models[$table] ?? null, $active);
        }

        $ordered = [];
        $resolved = [];
        $pending = $tables;

        while ($pending !== []) {
            $progress = false;

            foreach ($pending as $index => $table) {
                if (array_diff($dependencies[$table], array_keys($resolved)) !== []) {
                    continue;
                }

                $ordered[] = $table;
                $resolved[$table] = true;
                unset($pending[$index]);
                $progress = true;
            }

            if (!$progress) {
                return array_merge($ordered, array_values($pending));
            }

            $pending = array_values($pending);
        }

        return $ordered;
    }

    /**
     * @param class-string<Model>|null $modelClass
     * @param array<string, bool> $active
     * @return string[]
     */
    private static function dependencies(string $table, ?string $modelClass, array $active): array
    {
        if ($modelClass === null || !is_subclass_of($modelClass, Model::class)) {
            return [];
        }

        $dependencies = [];

        foreach ($modelClass::getColumns() as $column) {
            $foreignTable = is_array($column)
                ? trim((string) ($column['foreign_table'] ?? ''))
                : '';

            if ($foreignTable === '' || $foreignTable === $table || !isset($active[$foreignTable])) {
                continue;
            }

            $dependencies[$foreignTable] = true;
        }

        return array_keys($dependencies);
    }
}
