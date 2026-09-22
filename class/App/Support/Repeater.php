<?php

namespace Wonder\App\Support;

use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\Table;

final class Repeater
{
    public static function hasRowsInRequest(string $name, array $post, array $files = []): bool
    {
        return is_array($post[$name] ?? null) || is_array($files[$name]['name'] ?? null);
    }

    public static function rowsFromRequest(string $name, array $post, array $files = []): array
    {
        $rows = is_array($post[$name] ?? null) ? $post[$name] : [];
        $fileRows = static::filesFromRequest($name, $files);

        foreach ($fileRows as $rowKey => $columns) {
            if (!isset($rows[$rowKey]) || !is_array($rows[$rowKey])) {
                $rows[$rowKey] = [];
            }

            foreach ($columns as $columnName => $fileBag) {
                $rows[$rowKey][$columnName] = $fileBag;
            }
        }

        return array_values(array_filter(
            $rows,
            static fn ($row) => is_array($row) && !static::isEmptyRow($row)
        ));
    }

    /**
     * La condizione con cui si leggono (e si cancellano) le righe di un
     * repeater: il padre, la fetta dichiarata e, se c'è, il soft delete.
     *
     * Sta in un metodo suo perché lettura e sincronizzazione devono usare la
     * **stessa**: se la cancellazione guardasse più righe della lettura,
     * toglierebbe quelle che il repeater non ha mai mostrato.
     *
     * @return array<string, mixed>
     */
    public static function relationCondition(RepeaterRelation $relation, int|string $parentId): array
    {
        $condition = array_merge($relation->condition, [$relation->parentKey => $parentId]);

        if ($relation->softDelete) {
            $condition[$relation->deletedColumn] = 'false';
        }

        return $condition;
    }

    public static function loadRelatedRows(
        RepeaterRelation $relation,
        int|string $parentId
    ): array {
        $condition = static::relationCondition($relation, $parentId);

        $result = sqlSelect(
            $relation->table,
            $condition,
            null,
            $relation->positionKey ?? null,
            $relation->positionKey !== null ? 'ASC' : null
        );

        $rows = is_array($result->row ?? null) ? $result->row : [];

        return array_values(array_filter(
            $rows,
            static fn ($row) => is_array($row)
        ));
    }

    public static function filesFromRequest(string $name, array $files = []): array
    {
        $bag = $files[$name] ?? null;

        if (!is_array($bag) || !isset($bag['name']) || !is_array($bag['name'])) {
            return [];
        }

        $rows = [];

        foreach ($bag['name'] as $rowKey => $columns) {
            if (!is_array($columns)) {
                continue;
            }

            foreach ($columns as $columnName => $names) {
                $rows[$rowKey][$columnName] = [
                    'name' => is_array($names) ? $names : [$names],
                    'type' => is_array($bag['type'][$rowKey][$columnName] ?? null)
                        ? $bag['type'][$rowKey][$columnName]
                        : [($bag['type'][$rowKey][$columnName] ?? '')],
                    'tmp_name' => is_array($bag['tmp_name'][$rowKey][$columnName] ?? null)
                        ? $bag['tmp_name'][$rowKey][$columnName]
                        : [($bag['tmp_name'][$rowKey][$columnName] ?? '')],
                    'error' => is_array($bag['error'][$rowKey][$columnName] ?? null)
                        ? $bag['error'][$rowKey][$columnName]
                        : [($bag['error'][$rowKey][$columnName] ?? 4)],
                    'size' => is_array($bag['size'][$rowKey][$columnName] ?? null)
                        ? $bag['size'][$rowKey][$columnName]
                        : [($bag['size'][$rowKey][$columnName] ?? 0)],
                ];
            }
        }

        return $rows;
    }

    public static function syncRelatedRows(
        RepeaterRelation $relation,
        int|string $parentId,
        array $rows,
        ?callable $prepareRow = null
    ): array {
        $summary = [
            'inserted' => [],
            'updated' => [],
            'deleted' => [],
        ];

        $rowKey = $relation->rowKey;
        $existingCondition = static::relationCondition($relation, $parentId);

        $existingRows = (array) sqlSelect(
            $relation->table,
            $existingCondition,
            null,
            $relation->positionKey ?? null,
            $relation->positionKey !== null ? 'ASC' : null
        )->row;

        $existingById = [];

        foreach ($existingRows as $existingRow) {
            if (!is_array($existingRow)) {
                continue;
            }

            $existingId = $existingRow[$rowKey] ?? null;

            if ($existingId !== null && trim((string) $existingId) !== '') {
                $existingById[(string) $existingId] = $existingRow;
            }
        }

        $seenIds = [];
        $position = 0;

        foreach ($rows as $row) {
            if (!is_array($row)) {
                continue;
            }

            $payload = $row;
            $rawId = $payload[$rowKey] ?? null;
            $hasId = $rawId !== null && trim((string) $rawId) !== '';

            if ($hasId) {
                $seenIds[] = (string) $rawId;
            }

            $payload = array_merge($relation->condition, $payload);
            $payload[$relation->parentKey] = $parentId;

            // La fetta vince su quello che arriva dal form: una riga di questo
            // repeater appartiene a questa fetta per definizione.
            foreach ($relation->condition as $colonna => $valore) {
                $payload[$colonna] = $valore;
            }

            if ($relation->positionKey !== null) {
                $payload[$relation->positionKey] = $position;
            }

            if ($relation->softDelete && !array_key_exists($relation->deletedColumn, $payload)) {
                $payload[$relation->deletedColumn] = 'false';
            }

            if ($prepareRow !== null) {
                $payload = (array) $prepareRow(
                    $payload,
                    $row,
                    $hasId ? ($existingById[(string) $rawId] ?? null) : null
                );
            }

            $payload = static::preparePayload(
                $relation,
                $payload,
                $hasId ? ($existingById[(string) $rawId] ?? null) : null
            );

            unset($payload[$rowKey]);

            if ($hasId) {
                sqlModify($relation->table, $payload, $rowKey, $rawId);
                $summary['updated'][] = (string) $rawId;
            } else {
                $insert = sqlInsert($relation->table, $payload);
                $summary['inserted'][] = $insert->insert_id ?? null;
            }

            $position++;
        }

        foreach ($existingById as $existingId => $existingRow) {
            // Le chiavi numeriche diventano interi: si confronta come stringa.
            $existingId = (string) $existingId;

            if (in_array($existingId, $seenIds, true)) {
                continue;
            }

            if ($relation->softDelete) {
                sqlModify(
                    $relation->table,
                    [$relation->deletedColumn => 'true'],
                    $rowKey,
                    $existingId
                );
            } else {
                sqlDelete($relation->table, [$rowKey => $existingId]);
            }

            $summary['deleted'][] = $existingId;
        }

        return $summary;
    }

    private static function preparePayload(
        RepeaterRelation $relation,
        array $payload,
        ?array $existingRow = null
    ): array {
        $schemaName = trim((string) ($relation->schemaName ?? ''));
        $prepareTable = trim((string) ($relation->prepareTable ?? $relation->table));
        $folder = trim((string) ($relation->folder ?? ''), '/');
        $previousName = LegacyGlobals::get('NAME');

        $prepared = $payload;

        if ($schemaName !== '' && array_key_exists($schemaName, Table::$list)) {
            LegacyGlobals::set('NAME', (object) [
                'table' => $prepareTable,
                'folder' => $folder,
                'schema' => $schemaName,
            ]);

            $prepared = Table::key($schemaName)->prepareFor($prepareTable, $payload, $existingRow);
        } elseif ($prepareTable !== '' && array_key_exists($prepareTable, Table::$list)) {
            LegacyGlobals::set('NAME', (object) [
                'table' => $prepareTable,
                'folder' => $folder,
                'schema' => $prepareTable,
            ]);

            $prepared = Table::key($prepareTable)->prepare($payload, $existingRow);
        }

        LegacyGlobals::set('NAME', $previousName);

        return $prepared;
    }

    protected static function isEmptyRow(array $row): bool
    {
        foreach ($row as $key => $value) {
            // Il campo file porta con sé l'elenco dei file già caricati, e
            // quando non ce n'è nessuno vale la stringa `[]`: presa per un
            // valore qualsiasi, teneva in piedi righe senza niente dentro.
            if (is_string($key) && str_ends_with($key, '__wi_files')) {
                $decoded = json_decode((string) $value, true);

                if (is_array($decoded) && $decoded !== []) {
                    return false;
                }

                continue;
            }

            if (is_array($value)) {
                // Un campo file posta sempre la sua busta, anche quando non
                // hai scelto niente: `name` vuoto ma `error` 4 e `size` 0.
                // Guardare la busta intera faceva passare per piena una riga
                // in cui non c'era nessun file.
                if (array_key_exists('name', $value)) {
                    $names = is_array($value['name']) ? $value['name'] : [$value['name']];

                    foreach ($names as $fileName) {
                        if (trim((string) $fileName) !== '') {
                            return false;
                        }
                    }

                    continue;
                }

                // Un array di valori tutti vuoti è vuoto: un campo file in una
                // riga nuova posta `['']`, e prendere quello per "riga piena"
                // creava una riga senza niente dentro a ogni salvataggio.
                if (!static::isEmptyRow($value)) {
                    return false;
                }

                continue;
            }

            if (trim((string) $value) !== '') {
                return false;
            }
        }

        return true;
    }
}
