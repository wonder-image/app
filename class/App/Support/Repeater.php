<?php

namespace Wonder\App\Support;

use Wonder\App\ResourceSchema\RepeaterRelation;

final class Repeater
{
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
        $existingCondition = [$relation->parentKey => $parentId];

        if ($relation->softDelete) {
            $existingCondition[$relation->deletedColumn] = 'false';
        }

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

            $payload[$relation->parentKey] = $parentId;

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

    private static function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (is_array($value)) {
                if (isset($value['name']) && is_array($value['name'])) {
                    foreach ($value['name'] as $fileName) {
                        if (trim((string) $fileName) !== '') {
                            return false;
                        }
                    }

                    continue;
                }

                if ($value !== []) {
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
