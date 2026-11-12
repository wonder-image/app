<?php

namespace Wonder\App\Media;

use RuntimeException;
use Wonder\App\Models\System\ImageResize;
use Wonder\App\Support\Mail\DatabaseOutboxStore;
use Wonder\Sql\Connection;

/**
 * Coda in `image_resizes`, solo prepared statement: la presa in carico è un
 * `UPDATE` condizionato, quindi due processi non lavorano la stessa riga.
 */
class DatabaseResizeStore implements ResizeStore
{
    private const TABLE = 'image_resizes';

    public function __construct(private ?\mysqli $connection = null)
    {
    }

    public function usable(): bool
    {
        return function_exists('sqlTableExists') && sqlTableExists(ImageResize::$table);
    }

    public function heartbeat(): ?string
    {
        // Lo stesso heartbeat che legge la coda email.
        return (new DatabaseOutboxStore($this->connection()))->heartbeat();
    }

    public function put(string $path, array $job, string $now): void
    {
        $this->execute(
            'INSERT INTO '.self::TABLE.' (path, path_hash, widths, webp, quality, delete_when_ready, status, attempts, error, queued_at, next_attempt_at, claim_token, claimed_until, finished_at)'
            ." VALUES (?, ?, ?, ?, ?, ?, 'pending', 0, '', ?, ?, NULL, NULL, NULL)"
            .' ON DUPLICATE KEY UPDATE path = VALUES(path), widths = VALUES(widths), webp = VALUES(webp), quality = VALUES(quality), delete_when_ready = VALUES(delete_when_ready),'
            ." status = 'pending', attempts = 0, error = '', queued_at = VALUES(queued_at), next_attempt_at = VALUES(next_attempt_at), claim_token = NULL, claimed_until = NULL, finished_at = NULL",
            [
                $path,
                sha1($path),
                json_encode(array_values((array) ($job['widths'] ?? [])), JSON_THROW_ON_ERROR),
                !empty($job['webp']) ? 'true' : 'false',
                isset($job['quality']) ? (int) $job['quality'] : null,
                json_encode(array_values((array) ($job['delete'] ?? [])), JSON_THROW_ON_ERROR),
                $now,
                $now,
            ]
        );
    }

    public function find(string $path): ?array
    {
        $row = $this->execute('SELECT status, error, delete_when_ready FROM '.self::TABLE.' WHERE path_hash = ?', [sha1($path)])->get_result()->fetch_assoc();

        return is_array($row)
            ? ['status' => (string) $row['status'], 'error' => (string) ($row['error'] ?? ''), 'delete' => self::decode($row['delete_when_ready'] ?? null)]
            : null;
    }

    public function release(string $now, int $maxAttempts, string $error): int
    {
        $failed = $this->execute(
            'UPDATE '.self::TABLE." SET status = 'failed', error = ?, claim_token = NULL, claimed_until = NULL, finished_at = ? WHERE status = 'pending' AND claim_token IS NOT NULL AND claimed_until <= ? AND attempts >= ?",
            [$error, $now, $now, $maxAttempts]
        )->affected_rows;

        $freed = $this->execute(
            'UPDATE '.self::TABLE." SET claim_token = NULL, claimed_until = NULL, next_attempt_at = ? WHERE status = 'pending' AND claim_token IS NOT NULL AND claimed_until <= ?",
            [$now, $now]
        )->affected_rows;

        return $failed + $freed;
    }

    public function due(string $now, int $limit): array
    {
        $rows = $this->execute(
            'SELECT id FROM '.self::TABLE." WHERE status = 'pending' AND claim_token IS NULL AND next_attempt_at <= ? ORDER BY next_attempt_at, id LIMIT ?",
            [$now, $limit]
        )->get_result()->fetch_all(MYSQLI_ASSOC);

        return array_map(static fn (array $row): int => (int) $row['id'], $rows);
    }

    public function claim(int $id, string $token, string $until): ?array
    {
        $claimed = $this->execute(
            'UPDATE '.self::TABLE." SET claim_token = ?, claimed_until = ?, attempts = attempts + 1 WHERE id = ? AND status = 'pending' AND claim_token IS NULL",
            [$token, $until, $id]
        )->affected_rows;

        if ($claimed !== 1) {
            return null;
        }

        $row = $this->execute('SELECT path, widths, webp, quality, delete_when_ready, attempts FROM '.self::TABLE.' WHERE id = ? AND claim_token = ?', [$id, $token])->get_result()->fetch_assoc();

        if (!is_array($row)) {
            return null;
        }

        return [
            'path' => (string) $row['path'],
            'widths' => self::decode($row['widths'] ?? null),
            'webp' => ($row['webp'] ?? 'true') === 'true',
            'quality' => $row['quality'] !== null ? (int) $row['quality'] : null,
            'delete' => self::decode($row['delete_when_ready'] ?? null),
            'attempts' => (int) $row['attempts'],
        ];
    }

    public function finish(int $id, string $token, string $status, string $error, string $now, ?string $nextAttempt = null): bool
    {
        if ($status === ResizeQueue::PENDING) {
            $statement = $this->execute(
                'UPDATE '.self::TABLE.' SET error = ?, next_attempt_at = ?, claim_token = NULL, claimed_until = NULL WHERE id = ? AND claim_token = ?',
                [$error, $nextAttempt ?? $now, $id, $token]
            );
        } else {
            $statement = $this->execute(
                'UPDATE '.self::TABLE." SET status = ?, error = ?, finished_at = ?, delete_when_ready = IF(? = 'ready', NULL, delete_when_ready), claim_token = NULL, claimed_until = NULL WHERE id = ? AND claim_token = ?",
                [$status, $error, $now, $status, $id, $token]
            );
        }

        return $statement->affected_rows === 1;
    }

    public function retryFailed(string $now): int
    {
        return $this->execute(
            'UPDATE '.self::TABLE." SET status = 'pending', attempts = 0, error = '', next_attempt_at = ?, queued_at = ?, claim_token = NULL, claimed_until = NULL, finished_at = NULL WHERE status = 'failed'",
            [$now, $now]
        )->affected_rows;
    }

    public function oldestPending(): ?string
    {
        $row = $this->execute('SELECT MIN(queued_at) AS queued FROM '.self::TABLE." WHERE status = 'pending'")->get_result()->fetch_assoc();

        return $row['queued'] ?? null;
    }

    public function failed(string $since, string $except): int
    {
        $row = $this->execute('SELECT COUNT(*) AS total FROM '.self::TABLE." WHERE status = 'failed' AND finished_at >= ? AND error <> ?", [$since, $except])->get_result()->fetch_assoc();

        return (int) ($row['total'] ?? 0);
    }

    private static function decode(mixed $json): array
    {
        $list = is_string($json) ? json_decode($json, true) : null;

        return is_array($list) ? array_values($list) : [];
    }

    private function connection(): \mysqli
    {
        return $this->connection ??= ($GLOBALS['mysqli'] ?? null) ?? Connection::Connect('main');
    }

    private function execute(string $sql, array $parameters = []): \mysqli_stmt
    {
        $statement = $this->connection()->prepare($sql);

        if (!$statement || !$statement->execute($parameters)) {
            throw new RuntimeException('Operazione sulla coda dei ridimensionamenti fallita.');
        }

        return $statement;
    }
}
