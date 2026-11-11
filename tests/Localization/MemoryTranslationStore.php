<?php

// Di supporto ai test: eseguito da solo carica l'autoload e non fa altro.
require_once __DIR__.'/../../vendor/autoload.php';

use Wonder\Localization\Content\{TranslationStore, Translations};

/**
 * Store in memoria per i test: `$sources[tabella][id]` sono le righe delle
 * tabelle sorgente, `$rows` le traduzioni.
 */
final class MemoryTranslationStore implements TranslationStore
{
    public array $sources = [];
    public array $rows = [];
    public array $memory = [];
    public array $terms = [];
    public int $queries = 0;

    public function translations(string $table, array $rowIds, string $lang): array
    {
        $this->queries++;
        $result = [];

        foreach ($this->rows as $row) {
            if ($row['table_name'] === $table && $row['language_code'] === $lang && in_array($row['row_id'], $rowIds, true)) {
                $result[$row['row_id']][$row['field']] = $row;
            }
        }

        return $result;
    }

    public function save(string $table, int $rowId, string $field, string $lang, array $values): void
    {
        $key = "{$table}|{$rowId}|{$field}|{$lang}";
        $current = $this->rows[$key] ?? ['id' => count($this->rows) + 1, 'table_name' => $table, 'row_id' => $rowId, 'field' => $field, 'language_code' => $lang, 'value' => ''];
        $values += ['status' => 'automatic', 'source_hash' => null, 'engine' => ''];

        if (($values['value'] ?? null) === null) {
            unset($values['value']);
        }

        $this->rows[$key] = $values + $current;
    }

    public function pending(string $table, string $field, string $lang, int $limit, bool $softDelete): array
    {
        $result = [];

        foreach ($this->sources[$table] ?? [] as $id => $source) {
            $value = (string) ($source[$field] ?? '');

            if ($value === '' || ($softDelete && ($source['deleted'] ?? 'false') !== 'false')) {
                continue;
            }

            $translation = $this->rows["{$table}|{$id}|{$field}|{$lang}"] ?? null;

            if ($translation !== null && ($translation['status'] === 'review'
                || ($translation['source_hash'] !== null && $translation['source_hash'] === Translations::hash($value)))) {
                continue;
            }

            $result[] = ['id' => $id, 'value' => $value, 'status' => $translation['status'] ?? null, 'source_hash' => $translation['source_hash'] ?? null];

            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    public function sourceRows(string $table, array $fields, bool $softDelete): array
    {
        $result = [];

        foreach ($this->sources[$table] ?? [] as $id => $row) {
            if (!$softDelete || ($row['deleted'] ?? 'false') === 'false') {
                $result[$id] = array_map('strval', array_intersect_key($row + array_fill_keys($fields, ''), array_flip($fields)));
            }
        }

        ksort($result);

        return $result;
    }

    public function memory(string $hash): ?string
    {
        return $this->memory[$hash]['value'] ?? null;
    }

    public function remember(string $hash, string $from, string $to, string $source, string $value, string $engine): void
    {
        $this->memory[$hash] = compact('from', 'to', 'source', 'value', 'engine');
    }

    public function terms(?string $status = null): array
    {
        return array_values(array_filter($this->terms, static fn (array $term): bool => $status === null || $term['status'] === $status));
    }

    public function saveTerm(string $term, array $values): int
    {
        $current = $this->terms[$term] ?? ['id' => count($this->terms) + 1, 'term' => $term, 'mode' => 'glossary', 'status' => 'confirmed', 'occurrences' => 0];
        $this->terms[$term] = $values + $current;

        return $this->terms[$term]['id'];
    }

    public function usages(string $table, string $field, string $term, bool $softDelete): array
    {
        $result = [];

        foreach ($this->rows as $row) {
            $source = (string) ($this->sources[$table][$row['row_id']][$field] ?? '');

            if ($row['table_name'] === $table && $row['field'] === $field && mb_stripos($source, $term) !== false) {
                $result[] = ['id' => $row['id'], 'row_id' => $row['row_id'], 'language_code' => $row['language_code'], 'status' => $row['status']];
            }
        }

        return $result;
    }

    public function requeue(array $ids): int
    {
        $count = 0;

        foreach ($this->rows as $key => $row) {
            if (in_array($row['id'], $ids, true) && $row['status'] === 'automatic') {
                $this->rows[$key]['source_hash'] = null;
                $count++;
            }
        }

        return $count;
    }
}
