<?php

namespace Wonder\App\Import;

use mysqli;
use Wonder\App\Models\Import\ImportChange;
use Wonder\App\Models\Import\ImportJob;
use Wonder\App\Models\Import\ImportRow;

/**
 * Le tre tabelle del caricamento, in SQL diretto: le righe sono tante, i
 * JSON non passano dal `dataSchema()` e nessuna di queste scritture è
 * un dato del catalogo.
 */
final class Store
{
    private const CHUNK = 500;

    /** @param array<string, mixed> $values */
    public static function createJob(array $values): int
    {
        $db = self::db();
        [$columns, $sql] = self::assignments($db, $values);
        $db->query('INSERT INTO `'.ImportJob::$table.'` ('.implode(', ', $columns).') VALUES ('.implode(', ', $sql).')');

        return (int) $db->insert_id;
    }

    /** @return array<string, mixed>|null */
    public static function job(int $id, bool $forUpdate = false): ?array
    {
        $result = self::db()->query('SELECT * FROM `'.ImportJob::$table.'` WHERE id = '.$id.' AND deleted = \'false\''.($forUpdate ? ' FOR UPDATE' : ''));
        $row = $result instanceof \mysqli_result ? $result->fetch_assoc() : null;

        return is_array($row) ? $row : null;
    }

    /** @param array<string, mixed> $values */
    public static function updateJob(int $id, array $values): void
    {
        $db = self::db();
        [$columns, $sql] = self::assignments($db, $values);
        $set = array_map(static fn (string $column, string $value): string => $column.' = '.$value, $columns, $sql);
        $db->query('UPDATE `'.ImportJob::$table.'` SET '.implode(', ', $set).' WHERE id = '.$id);
    }

    /**
     * Gli ultimi lavori di un tracciato, dal più recente.
     *
     * @return list<array<string, mixed>>
     */
    public static function recentJobs(string $layout, int $limit = 10): array
    {
        $db = self::db();

        return self::fetch($db->query('SELECT * FROM `'.ImportJob::$table.'` WHERE deleted = \'false\' AND layout = '.self::quote($db, $layout).' ORDER BY id DESC LIMIT '.max(1, $limit)));
    }

    /** @return list<int> */
    public static function runningJobs(): array
    {
        $rows = self::fetch(self::db()->query('SELECT id FROM `'.ImportJob::$table.'` WHERE deleted = \'false\' AND status = \'running\' ORDER BY id'));

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public static function deleteRows(int $jobId): void
    {
        self::db()->query('DELETE FROM `'.ImportRow::$table.'` WHERE import_job_id = '.$jobId);
    }

    /**
     * @param list<array{line: int, data: array<string, string>, status: string, action: string, errors: list<string>, warnings: list<string>}> $rows
     */
    public static function insertRows(int $jobId, array $rows): void
    {
        $db = self::db();

        foreach (array_chunk($rows, self::CHUNK) as $chunk) {
            $values = [];

            foreach ($chunk as $row) {
                $values[] = '('.implode(', ', [
                    $jobId,
                    (int) $row['line'],
                    self::quote($db, self::json($row['data'])),
                    self::quote($db, $row['status']),
                    self::quote($db, $row['action']),
                    self::quote($db, self::json($row['errors'])),
                    self::quote($db, self::json($row['warnings'])),
                ]).')';
            }

            $db->query('INSERT INTO `'.ImportRow::$table.'` (import_job_id, line, data, status, action, errors, warnings) VALUES '.implode(', ', $values));
        }
    }

    /**
     * Righe di un lavoro, nell'ordine del file.
     *
     * @param list<string> $statuses vuoto = tutte
     * @return list<array<string, mixed>>
     */
    public static function rows(int $jobId, array $statuses = [], ?int $limit = null, bool $withProblems = false): array
    {
        $db = self::db();
        $where = 'import_job_id = '.$jobId;

        if ($statuses !== []) {
            $where .= ' AND status IN ('.implode(', ', array_map(static fn (string $status): string => self::quote($db, $status), $statuses)).')';
        }

        if ($withProblems) {
            $where .= " AND (status = 'error' OR (warnings IS NOT NULL AND warnings <> '[]'))";
        }

        return self::fetch($db->query('SELECT * FROM `'.ImportRow::$table.'` WHERE '.$where.' ORDER BY line, id'.($limit !== null ? ' LIMIT '.max(1, $limit) : '')));
    }

    /** @param list<string> $errors @param list<string> $warnings */
    public static function updateRow(int $id, string $status, string $action, array $errors = [], ?array $warnings = null): void
    {
        $db = self::db();
        $set = 'status = '.self::quote($db, $status).', action = '.self::quote($db, $action).', errors = '.self::quote($db, self::json($errors));

        if ($warnings !== null) {
            $set .= ', warnings = '.self::quote($db, self::json($warnings));
        }

        $db->query('UPDATE `'.ImportRow::$table.'` SET '.$set.' WHERE id = '.$id);
    }

    /** @return array<string, int> righe per stato */
    public static function rowCounts(int $jobId): array
    {
        $counts = [];

        foreach (self::fetch(self::db()->query('SELECT status, action, COUNT(*) AS total FROM `'.ImportRow::$table.'` WHERE import_job_id = '.$jobId.' GROUP BY status, action')) as $row) {
            $counts[(string) $row['status']] = ($counts[(string) $row['status']] ?? 0) + (int) $row['total'];
            $counts['action:'.$row['action']] = ($counts['action:'.$row['action']] ?? 0) + (int) $row['total'];
        }

        return $counts;
    }

    public static function addChange(int $jobId, int $rowId, Change $change): void
    {
        $db = self::db();
        $db->query('INSERT INTO `'.ImportChange::$table.'` (import_job_id, import_row_id, target, record_id, action, old_values, stock_movement_id) VALUES ('.implode(', ', [
            $jobId,
            $rowId,
            self::quote($db, $change->target),
            $change->recordId,
            self::quote($db, $change->action),
            self::quote($db, self::json($change->oldValues)),
            $change->stockMovementId,
        ]).')');
    }

    /** @return list<Change> dall'ultima alla prima */
    public static function changes(int $jobId): array
    {
        $rows = self::fetch(self::db()->query('SELECT * FROM `'.ImportChange::$table.'` WHERE import_job_id = '.$jobId.' AND deleted = \'false\' ORDER BY id DESC'));

        return array_map([Change::class, 'fromRow'], $rows);
    }

    /** @return list<array<string, mixed>> */
    private static function fetch(mixed $result): array
    {
        if (!$result instanceof \mysqli_result) {
            return [];
        }

        $rows = [];

        while ($row = $result->fetch_assoc()) {
            $rows[] = $row;
        }

        return $rows;
    }

    /**
     * @param array<string, mixed> $values
     * @return array{0: list<string>, 1: list<string>}
     */
    private static function assignments(mysqli $db, array $values): array
    {
        $columns = [];
        $sql = [];

        foreach ($values as $column => $value) {
            $columns[] = '`'.str_replace('`', '', (string) $column).'`';
            $sql[] = match (true) {
                $value === null => 'NULL',
                is_int($value) => (string) $value,
                is_array($value) => self::quote($db, self::json($value)),
                default => self::quote($db, (string) $value),
            };
        }

        return [$columns, $sql];
    }

    private static function quote(mysqli $db, string $value): string
    {
        return "'".$db->real_escape_string($value)."'";
    }

    private static function json(mixed $value): string
    {
        return (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    }

    private static function db(): mysqli
    {
        return ImportJob::connection();
    }
}
