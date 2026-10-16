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
                    $this->execute("INSERT INTO scheduler_schedules (name, task_key, expression, timezone, parameters, enabled, requested, next_due, origin) VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'code')",
                        [$task->label(), $task->key(), $task->expression(), 'Europe/Rome', json_encode((object) $task->defaultParameters(), JSON_THROW_ON_ERROR), $task->enabled() ? 'true' : 'false', 'false', self::next($task->expression(), 'Europe/Rome')]);
                }
                if ($this->state('default-origin:'.$task->key()) === null) {
                    $this->execute("UPDATE scheduler_schedules SET origin = 'code' WHERE task_key = ? ORDER BY id LIMIT 1", [$task->key()]);
                    $this->setState('default-origin:'.$task->key(), 'migrated');
                }
                if (TaskRegistry::alwaysEnabled($task->key())) {
                    foreach ($this->rows("SELECT id, expression, timezone FROM scheduler_schedules WHERE task_key = ? AND origin = 'code' AND deleted = 'false' AND enabled <> 'true'", [$task->key()]) as $row) {
                        $this->execute("UPDATE scheduler_schedules SET enabled = 'true', next_due = ? WHERE id = ?", [self::next($row['expression'], $row['timezone']), $row['id']]);
                    }
                }
                $this->connection->commit();
            } catch (\Throwable $error) {
                $this->connection->rollback();
                throw $error;
            }
        }
    }

    public function find(int $id): ?array
    {
        return $this->rows("SELECT * FROM scheduler_schedules WHERE id = ? AND deleted = 'false'", [$id])[0] ?? null;
    }

    /** Pianificazione predefinita di un'attività che non si può sospendere. */
    public static function isAlwaysEnabled(array $row): bool
    {
        return ($row['origin'] ?? '') === 'code' && TaskRegistry::alwaysEnabled((string) ($row['task_key'] ?? ''));
    }

    public function toggle(int $id, bool $enabled): array
    {
        $row = $this->find($id) ?? throw new \InvalidArgumentException('Pianificazione non trovata.');
        if (!$enabled && self::isAlwaysEnabled($row)) {
            throw new \InvalidArgumentException('Questa pianificazione è sempre attiva e non si può sospendere.');
        }
        if ($enabled) {
            $this->execute("UPDATE scheduler_schedules SET enabled = 'true', requested = 'false', next_due = ? WHERE id = ?",
                [self::next($row['expression'], $row['timezone']), $id]);
        } else {
            $this->execute("UPDATE scheduler_schedules SET enabled = 'false', requested = 'false' WHERE id = ?", [$id]);
        }
        return $this->find($id);
    }

    /**
     * Chiede l'esecuzione al prossimo tick e restituisce l'ultima registrazione
     * già esistente: le esecuzioni con id maggiore sono quelle richieste ora.
     */
    public function request(int $id): int
    {
        $row = $this->find($id);
        if (!$row || $row['enabled'] !== 'true') { throw new \InvalidArgumentException('Pianificazione assente o sospesa.'); }
        ConfiguredTask::resolve($row);
        $after = (int) ($this->rows('SELECT COALESCE(MAX(id), 0) AS id FROM scheduler_runs WHERE schedule_id = ?', [$id])[0]['id'] ?? 0);
        $this->execute("UPDATE scheduler_schedules SET requested = 'true' WHERE id = ?", [$id]);
        return $after;
    }

    /** Prima esecuzione successiva a `$after`, per seguire una richiesta manuale. */
    public function runStatus(int $id, int $after): array
    {
        $row = $this->find($id) ?? throw new \InvalidArgumentException('Pianificazione non trovata.');
        $run = $this->rows('SELECT id, status, started_at, finished_at, duration_ms, output FROM scheduler_runs WHERE schedule_id = ? AND id > ? ORDER BY id LIMIT 1', [$id, $after])[0] ?? null;
        return [
            'requested' => $row['requested'] === 'true',
            'timeout' => ConfiguredTask::resolve($row)->timeout(),
            'heartbeat' => $this->state('heartbeat'),
            'run' => $run,
        ];
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
            AVG(duration_ms) AS average_ms, COUNT(duration_ms) AS measured_runs, MAX(duration_ms) AS maximum_ms, SUM(duration_ms) AS total_ms,
            AVG(memory_bytes) AS average_memory, MAX(memory_bytes) AS maximum_memory,
            AVG(cpu_ms) AS average_cpu, SUM(cpu_ms) AS total_cpu
            FROM scheduler_runs WHERE started_at >= ? AND status NOT IN ('pending', 'running', 'skipped') GROUP BY task_key", [$cutoff]);
    }

    public function logCounts(int $days = 30): array
    {
        $days = max(1, min(self::RETENTION_DAYS, $days));
        // creation uses the database clock; scheduler timestamps are explicitly UTC.
        $mail = $this->rows("SELECT COUNT(*) AS total FROM mail_log WHERE deleted = 'false' AND status = 'sent' AND creation >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
        // Federated login emits login_success too: counting both would double it.
        $auth = $this->rows("SELECT COUNT(*) AS total FROM auth_log WHERE deleted = 'false' AND success = 1 AND event IN ('login_success', 'remember_login') AND creation >= DATE_SUB(NOW(), INTERVAL ? DAY)", [$days]);
        return ['emails_sent' => (int) $mail[0]['total'], 'logins' => (int) $auth[0]['total']];
    }
}
