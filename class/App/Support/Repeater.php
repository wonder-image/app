<?php

namespace Wonder\App\Support;

use Wonder\App\LegacyGlobals;
use Wonder\App\ResourceSchema\Input;
use Wonder\App\ResourceSchema\Inputs\InputPhonePrefix;
use Wonder\App\ResourceSchema\Inputs\InputRepeater;
use Wonder\App\ResourceSchema\Inputs\InputSelect;
use Wonder\App\ResourceSchema\RepeaterRelation;
use Wonder\App\Table;
use Wonder\Support\Prettify\Phone;

final class Repeater
{
    public static function hasRowsInRequest(string $name, array $post, array $files = []): bool
    {
        return is_array($post[$name] ?? null) || is_array($files[$name]['name'] ?? null);
    }

    /**
     * @param array<string, string|array<string, string>> $defaults Valori di
     *        default delle colonne ({@see columnDefaults()}): una riga che ha
     *        solo quelli è vuota. Sotto la chiave di un repeater interno
     *        stanno i default delle sue colonne.
     */
    public static function rowsFromRequest(string $name, array $post, array $files = [], array $defaults = []): array
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

        $rows = array_values(array_filter(
            $rows,
            static fn ($row) => is_array($row) && !static::isEmptyRow($row, $defaults)
        ));

        // Anche le righe vuote di un repeater interno si scartano.
        foreach ($defaults as $column => $innerDefaults) {
            if (!is_array($innerDefaults)) {
                continue;
            }

            foreach ($rows as $index => $row) {
                if (is_array($row[$column] ?? null)) {
                    $rows[$index][$column] = array_values(array_filter(
                        $row[$column],
                        static fn ($innerRow) => is_array($innerRow) && !static::isEmptyRow($innerRow, $innerDefaults)
                    ));
                }
            }
        }

        return $rows;
    }

    /**
     * I valori di default (`->value()`) delle colonne di un repeater.
     *
     * Una colonna precompilata, come la nazione su `IT`, posta il suo valore
     * anche nella riga che nessuno ha toccato: senza saperlo, quella riga
     * passava per piena e si salvava.
     *
     * @return array<string, string|array<string, string>>
     */
    public static function columnDefaults(object $field): array
    {
        if (!method_exists($field, 'get')) {
            return [];
        }

        $context = (array) ($field->get('context') ?? []);
        $defaults = [];

        foreach ((array) ($context['columns'] ?? []) as $column) {
            if (!$column instanceof Input) {
                continue;
            }

            // Un repeater interno porta i default delle sue colonne: servono
            // a riconoscere le sue righe vuote.
            if ($column instanceof InputRepeater) {
                $defaults[$column->name] = static::columnDefaults($column);

                continue;
            }

            $value = $column->get('value');

            // Una tendina senza scelta posta comunque la sua prima opzione.
            if (($value === null || $value === '') && $column instanceof InputSelect && !$column->get('multiple')) {
                $options = (array) ($column->get('options') ?? []);
                $value = $options !== [] ? (string) array_key_first($options) : null;
            }

            // Il prefisso vuoto parte da quello di casa (`InputPhonePrefix`).
            if (($value === null || $value === '') && $column instanceof InputPhonePrefix) {
                $value = Phone::$defaultCountryCode;
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                $defaults[$column->name] = trim((string) $value);
            }
        }

        return $defaults;
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

    /**
     * I file che le testate dei gruppi hanno postato, gruppo per gruppo
     * (vedi `InputRepeater::repeaterGroupFiles()`).
     *
     * Il manifesto elenca quello che resta: una stringa è un file già
     * salvato, un numero l'indice di uno appena caricato nella busta. Un
     * gruppo senza manifesto non compare: il suo campo non era in pagina, e
     * trattarlo come svuotato cancellerebbe i suoi file.
     *
     * @return array<string, array{manifest: list<mixed>, files: array<string, array<int|string, mixed>>|null}>
     */
    public static function groupFilesFromRequest(string $name, array $post = [], array $files = []): array
    {
        $posted = $post[$name] ?? null;

        if (!is_array($posted)) {
            return [];
        }

        $bag = $files[$name] ?? null;
        $groups = [];

        foreach ($posted as $key => $manifest) {
            $key = (string) $key;

            if (!str_ends_with($key, '__wi_files')) {
                continue;
            }

            $groupKey = substr($key, 0, -strlen('__wi_files'));
            $decoded = is_array($manifest) ? $manifest : json_decode((string) $manifest, true);

            if ($groupKey === '' || !is_array($decoded)) {
                continue;
            }

            $groups[$groupKey] = [
                'manifest' => array_values($decoded),
                'files' => static::groupFileBag($bag, $groupKey),
            ];
        }

        return $groups;
    }

    /**
     * La busta di `$_FILES` di un gruppo, nella forma di un campo singolo.
     *
     * @return array<string, array<int|string, mixed>>|null
     */
    private static function groupFileBag(mixed $bag, string $groupKey): ?array
    {
        if (!is_array($bag) || !is_array($bag['name'][$groupKey] ?? null)) {
            return null;
        }

        $defaults = ['name' => '', 'type' => '', 'tmp_name' => '', 'error' => 4, 'size' => 0];
        $out = [];

        foreach ($defaults as $field => $default) {
            $values = $bag[$field][$groupKey] ?? [];
            $out[$field] = is_array($values) ? $values : [$values ?? $default];
        }

        return $out;
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

            $deleted = $relation->softDelete ? ($payload[$relation->deletedColumn] ?? 'false') : null;

            $payload = static::preparePayload(
                $relation,
                $payload,
                $hasId ? ($existingById[(string) $rawId] ?? null) : null
            );

            // Lo schema dati non ha la colonna: una riga cancellata che torna resterebbe cancellata.
            if ($deleted !== null) {
                $payload[$relation->deletedColumn] = $deleted;
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

        if ($schemaName !== '' && Table::has($schemaName)) {
            LegacyGlobals::set('NAME', (object) [
                'table' => $prepareTable,
                'folder' => $folder,
                'schema' => $schemaName,
            ]);

            $prepared = Table::key($schemaName)->prepareFor($prepareTable, $payload, $existingRow);
        } elseif ($prepareTable !== '' && Table::has($prepareTable)) {
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

    protected static function isEmptyRow(array $row, array $defaults = []): bool
    {
        foreach ($row as $key => $value) {
            if (
                is_string($key)
                && is_scalar($value)
                && array_key_exists($key, $defaults)
                && trim((string) $value) === $defaults[$key]
            ) {
                continue;
            }

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

            // Le righe di un repeater interno: vuote se lo è ognuna.
            if (is_string($key) && is_array($value) && is_array($defaults[$key] ?? null)) {
                foreach ($value as $innerRow) {
                    if (is_array($innerRow) ? !static::isEmptyRow($innerRow, $defaults[$key]) : trim((string) $innerRow) !== '') {
                        return false;
                    }
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
