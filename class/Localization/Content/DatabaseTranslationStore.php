<?php

namespace Wonder\Localization\Content;

use RuntimeException;
use Wonder\App\Models\Localization\{Translation, TranslationTerm};
use Wonder\Sql\Connection;
use Wonder\Sql\Query;

/**
 * Store su MySQL con prepared statement. Tabelle e colonne delle sorgenti
 * vengono dai Model (codice), mai dalla richiesta, e passano comunque da
 * `Query::escapeIdentifier()`.
 */
class DatabaseTranslationStore implements TranslationStore
{
    private \mysqli $connection;

    public function __construct(?\mysqli $connection = null)
    {
        $this->connection = $connection ?? ($GLOBALS['mysqli'] ?? null) ?? Connection::Connect('main');
    }

    public function translations(string $table, array $rowIds, string $lang): array
    {
        $rowIds = array_values(array_unique(array_map('intval', $rowIds)));

        if ($rowIds === []) {
            return [];
        }

        $marks = implode(',', array_fill(0, count($rowIds), '?'));
        $rows = $this->fetch(
            "SELECT `id`, `row_id`, `field`, `value`, `status`, `source_hash` FROM `translations`
            WHERE `table_name` = ? AND `language_code` = ? AND `row_id` IN ({$marks})",
            [$table, $lang, ...$rowIds]
        );
        $result = [];

        foreach ($rows as $row) {
            $result[(int) $row['row_id']][(string) $row['field']] = $row;
        }

        return $result;
    }

    public function save(string $table, int $rowId, string $field, string $lang, array $values): void
    {
        $values += ['value' => null, 'status' => Translation::AUTOMATIC, 'source_hash' => null, 'engine' => ''];
        $now = date('Y-m-d H:i:s');

        $this->execute(
            "INSERT INTO `translations`
                (`table_name`, `row_id`, `field`, `language_code`, `value`, `status`, `source_hash`, `engine`, `translated_at`, `creation`, `last_modified`)
            VALUES (?, ?, ?, ?, COALESCE(?, ''), ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE
                `value` = COALESCE(VALUES(`value`), `value`), `status` = VALUES(`status`),
                `source_hash` = VALUES(`source_hash`), `engine` = VALUES(`engine`),
                `translated_at` = VALUES(`translated_at`), `last_modified` = VALUES(`last_modified`), `deleted` = 'false'",
            [$table, $rowId, $field, $lang, $values['value'], $values['status'], $values['source_hash'], $values['engine'], $now, $now, $now]
        );
    }

    public function pending(string $table, string $field, string $lang, int $limit, bool $softDelete): array
    {
        $source = Query::escapeIdentifier($table);
        $column = 's.'.Query::escapeIdentifier($field);
        $deleted = $softDelete ? "s.`deleted` = 'false' AND " : '';
        $limit = max(1, $limit);

        $rows = $this->fetch(
            "SELECT s.`id`, {$column} AS `value`, t.`status`, t.`source_hash`
            FROM {$source} s
            LEFT JOIN `translations` t
                ON t.`table_name` = ? AND t.`row_id` = s.`id` AND t.`field` = ? AND t.`language_code` = ?
            WHERE {$deleted}{$column} IS NOT NULL AND {$column} <> ''
                AND (t.`id` IS NULL OR (t.`status` <> 'review'
                    AND (t.`source_hash` IS NULL OR t.`source_hash` <> SHA2({$column}, 256))))
            ORDER BY s.`id`
            LIMIT {$limit}",
            [$table, $field, $lang]
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'value' => (string) $row['value'],
            'status' => $row['status'] === null ? null : (string) $row['status'],
            'source_hash' => $row['source_hash'] === null ? null : (string) $row['source_hash'],
        ], $rows);
    }

    public function sourceRows(string $table, array $fields, bool $softDelete): array
    {
        if ($fields === []) {
            return [];
        }

        $columns = implode(', ', array_map(static fn (string $field): string => Query::escapeIdentifier($field), $fields));
        $where = $softDelete ? " WHERE `deleted` = 'false'" : '';
        $result = [];

        foreach ($this->fetch('SELECT `id`, '.$columns.' FROM '.Query::escapeIdentifier($table).$where.' ORDER BY `id`') as $row) {
            $id = (int) $row['id'];
            unset($row['id']);
            $result[$id] = array_map(static fn (mixed $value): string => (string) $value, $row);
        }

        return $result;
    }

    public function memory(string $hash): ?string
    {
        $rows = $this->fetch('SELECT `value` FROM `translation_memory` WHERE `hash` = ? AND `deleted` = \'false\' LIMIT 1', [$hash]);

        return $rows === [] ? null : (string) $rows[0]['value'];
    }

    public function remember(string $hash, string $from, string $to, string $source, string $value, string $engine): void
    {
        $now = date('Y-m-d H:i:s');

        $this->execute(
            'INSERT INTO `translation_memory`
                (`hash`, `source_language`, `target_language`, `source`, `value`, `engine`, `creation`, `last_modified`)
            VALUES (?, ?, ?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `value` = VALUES(`value`), `engine` = VALUES(`engine`), `last_modified` = VALUES(`last_modified`)',
            [$hash, $from, $to, $source, $value, $engine, $now, $now]
        );
    }

    public function terms(?string $status = null): array
    {
        $sql = "SELECT `id`, `term`, `mode`, `status`, `occurrences` FROM `translation_terms` WHERE `deleted` = 'false'";
        $params = [];

        if ($status !== null) {
            $sql .= ' AND `status` = ?';
            $params[] = $status;
        }

        return $this->fetch($sql.' ORDER BY `term`', $params);
    }

    public function saveTerm(string $term, array $values): int
    {
        $values += ['mode' => TranslationTerm::GLOSSARY, 'status' => TranslationTerm::CONFIRMED, 'occurrences' => 0];
        $now = date('Y-m-d H:i:s');

        $this->execute(
            "INSERT INTO `translation_terms` (`term`, `mode`, `status`, `occurrences`, `creation`, `last_modified`)
            VALUES (?, ?, ?, ?, ?, ?)
            ON DUPLICATE KEY UPDATE `mode` = VALUES(`mode`), `status` = VALUES(`status`),
                `occurrences` = VALUES(`occurrences`), `last_modified` = VALUES(`last_modified`), `deleted` = 'false'",
            [$term, $values['mode'], $values['status'], (int) $values['occurrences'], $now, $now]
        );

        return (int) ($this->fetch('SELECT `id` FROM `translation_terms` WHERE `term` = ? LIMIT 1', [$term])[0]['id'] ?? 0);
    }

    public function usages(string $table, string $field, string $term, bool $softDelete): array
    {
        $source = Query::escapeIdentifier($table);
        $column = 's.'.Query::escapeIdentifier($field);
        $deleted = $softDelete ? "s.`deleted` = 'false' AND " : '';
        $like = '%'.strtr($term, ['!' => '!!', '%' => '!%', '_' => '!_']).'%';

        $rows = $this->fetch(
            "SELECT t.`id`, t.`row_id`, t.`language_code`, t.`status`
            FROM `translations` t
            JOIN {$source} s ON s.`id` = t.`row_id`
            WHERE {$deleted}t.`table_name` = ? AND t.`field` = ? AND {$column} LIKE ? ESCAPE '!'
            ORDER BY t.`row_id`, t.`language_code`",
            [$table, $field, $like]
        );

        return array_map(static fn (array $row): array => [
            'id' => (int) $row['id'],
            'row_id' => (int) $row['row_id'],
            'language_code' => (string) $row['language_code'],
            'status' => (string) $row['status'],
        ], $rows);
    }

    public function requeue(array $ids): int
    {
        $ids = array_values(array_unique(array_map('intval', $ids)));

        if ($ids === []) {
            return 0;
        }

        $marks = implode(',', array_fill(0, count($ids), '?'));
        $statement = $this->execute(
            "UPDATE `translations` SET `source_hash` = NULL WHERE `status` = 'automatic' AND `id` IN ({$marks})",
            $ids
        );

        return max(0, $statement->affected_rows);
    }

    /** @return list<array<string, mixed>> */
    private function fetch(string $sql, array $parameters = []): array
    {
        $result = $this->execute($sql, $parameters)->get_result();

        return $result === false ? [] : $result->fetch_all(MYSQLI_ASSOC);
    }

    private function execute(string $sql, array $parameters = []): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);

        if (!$statement || !$statement->execute($parameters)) {
            throw new RuntimeException('Operazione sulle traduzioni fallita.');
        }

        return $statement;
    }
}
