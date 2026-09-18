<?php

namespace Wonder\App\Scheduler;

use Cron\CronExpression;
use Wonder\Sql\Connection;

final class Repository
{
    public const RETENTION_DAYS = 180;
    public function __construct(private ?\mysqli $connection = null)
    {
        $this->connection ??= Connection::Connect('main');
    }

    public function execute(string $sql, array $parameters = []): \mysqli_stmt
    {
        $statement = $this->connection->prepare($sql);
        if (!$statement || !$statement->execute($parameters)) {
            throw new \RuntimeException('Operazione scheduler fallita.');
        }
        return $statement;
    }

    public function rows(string $sql, array $parameters = []): array
    {
        return $this->execute($sql, $parameters)->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function state(string $key): ?string
    {
        return $this->rows('SELECT value FROM scheduler_state WHERE state_key = ?', [$key])[0]['value'] ?? null;
    }

    public function setState(string $key, string $value): void
    {
        $this->execute('INSERT INTO scheduler_state (state_key, value) VALUES (?, ?) ON DUPLICATE KEY UPDATE value = VALUES(value)', [$key, $value]);
    }

    public static function next(string $expression, string $timezone): string
    {
        new \DateTimeZone($timezone);
        return (new CronExpression($expression))->getNextRunDate('now', 0, false, $timezone)
            ->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d H:i:s');
    }

    public function sync(): void
    {
        foreach (TaskRegistry::all() as $task) {
            $this->connection->begin_transaction();
            try {
                $insert = $this->execute('INSERT IGNORE INTO scheduler_state (state_key, value) VALUES (?, ?)', ['default:'.$task->key(), 'created']);
                if ($insert->affected_rows === 1) {
                    $this->execute('INSERT INTO scheduler_schedules (name, task_key, expression, timezone, parameters, enabled, requested, next_due) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
                        [$task->label(), $task->key(), $task->expression(), 'Europe/Rome', json_encode((object) $task->defaultParameters(), JSON_THROW_ON_ERROR), $task->enabled() ? 'true' : 'false', 'false', self::next($task->expression(), 'Europe/Rome')]);
                }
                $this->connection->commit();
            } catch (\Throwable $error) {
                $this->connection->rollback();
                throw $error;
            }
        }
    }

    public function request(int $id): void
    {
        $row = $this->rows("SELECT * FROM scheduler_schedules WHERE id = ? AND deleted = 'false'", [$id])[0] ?? null;
        if (!$row || $row['enabled'] !== 'true') { throw new \InvalidArgumentException('Pianificazione assente o sospesa.'); }
        TaskRegistry::get($row['task_key']);
        $this->execute("UPDATE scheduler_schedules SET requested = 'true' WHERE id = ?", [$id]);
    }

    public function cleanup(): void
    {
        if ($this->state('cleanup') === gmdate('Y-m-d')) { return; }
        $cutoff = gmdate('Y-m-d H:i:s', time() - self::RETENTION_DAYS * 86400);
        $deleted = $this->execute("DELETE FROM scheduler_runs WHERE started_at < ? AND status NOT IN ('running', 'pending') LIMIT 1000", [$cutoff])->affected_rows;
        if ($deleted < 1000) { $this->setState('cleanup', gmdate('Y-m-d')); }
    }

    public function statistics(int $days = 30): array
    {
        $cutoff = gmdate('Y-m-d H:i:s', time() - max(1, min(self::RETENTION_DAYS, $days)) * 86400);
        return $this->rows("SELECT task_key, COUNT(*) AS runs, SUM(status = 'success') AS successes,
            AVG(duration_ms) AS average_ms, MAX(duration_ms) AS maximum_ms, SUM(duration_ms) AS total_ms,
            AVG(memory_bytes) AS average_memory, MAX(memory_bytes) AS maximum_memory,
            AVG(cpu_ms) AS average_cpu, SUM(cpu_ms) AS total_cpu
            FROM scheduler_runs WHERE started_at >= ? AND status NOT IN ('pending', 'running', 'skipped') GROUP BY task_key", [$cutoff]);
    }
}
