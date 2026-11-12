<?php

namespace Wonder\App\Media;

use RuntimeException;

/**
 * Coda in memoria, con le stesse transizioni di `DatabaseResizeStore`: per i
 * test (`ResizeQueue::useStore()`), mai per un sito.
 */
final class MemoryResizeStore implements ResizeStore
{
    /** @var array<int, array<string, mixed>> */
    public array $rows = [];

    public bool $usable = true;

    /** Vero: `put()` solleva, come un database irraggiungibile. */
    public bool $broken = false;

    public function __construct(public ?string $beat = null)
    {
    }

    public function usable(): bool
    {
        return $this->usable;
    }

    public function heartbeat(): ?string
    {
        return $this->beat;
    }

    public function put(string $path, array $job, string $now): void
    {
        if ($this->broken) {
            throw new RuntimeException('Coda non scrivibile.');
        }

        $id = $this->id($path) ?? count($this->rows) + 1;

        $this->rows[$id] = [
            'path' => $path,
            'widths' => array_values((array) ($job['widths'] ?? [])),
            'webp' => !empty($job['webp']),
            'quality' => isset($job['quality']) ? (int) $job['quality'] : null,
            'delete' => array_values((array) ($job['delete'] ?? [])),
            'status' => ResizeQueue::PENDING,
            'attempts' => 0,
            'error' => '',
            'queued' => $now,
            'next' => $now,
            'token' => null,
            'until' => null,
            'finished' => null,
        ];
    }

    public function find(string $path): ?array
    {
        $id = $this->id($path);

        return $id === null ? null : [
            'status' => $this->rows[$id]['status'],
            'error' => $this->rows[$id]['error'],
            'delete' => $this->rows[$id]['delete'],
        ];
    }

    public function release(string $now, int $maxAttempts, string $error): int
    {
        $count = 0;

        foreach ($this->rows as &$row) {
            if ($row['status'] !== ResizeQueue::PENDING || $row['token'] === null || $row['until'] > $now) {
                continue;
            }

            if ($row['attempts'] >= $maxAttempts) {
                $row = array_merge($row, ['status' => ResizeQueue::FAILED, 'error' => $error, 'finished' => $now]);
            } else {
                $row['next'] = $now;
            }

            $row['token'] = $row['until'] = null;
            $count++;
        }

        return $count;
    }

    public function due(string $now, int $limit): array
    {
        $rows = array_filter($this->rows, static fn (array $row): bool => $row['status'] === ResizeQueue::PENDING && $row['token'] === null && $row['next'] <= $now);
        uasort($rows, static fn (array $a, array $b): int => $a['next'] <=> $b['next']);

        return array_slice(array_keys($rows), 0, $limit);
    }

    public function claim(int $id, string $token, string $until): ?array
    {
        $row = $this->rows[$id] ?? null;

        if ($row === null || $row['status'] !== ResizeQueue::PENDING || $row['token'] !== null) {
            return null;
        }

        $this->rows[$id] = $row = array_merge($row, ['token' => $token, 'until' => $until, 'attempts' => $row['attempts'] + 1]);

        return array_intersect_key($row, array_flip(['path', 'widths', 'webp', 'quality', 'delete', 'attempts']));
    }

    public function finish(int $id, string $token, string $status, string $error, string $now, ?string $nextAttempt = null): bool
    {
        if (($this->rows[$id]['token'] ?? null) !== $token) {
            return false;
        }

        $this->rows[$id] = array_merge($this->rows[$id], ['status' => $status, 'error' => $error, 'token' => null, 'until' => null], match ($status) {
            ResizeQueue::PENDING => ['next' => $nextAttempt ?? $now],
            ResizeQueue::READY => ['finished' => $now, 'delete' => []],
            default => ['finished' => $now],
        });

        return true;
    }

    public function retryFailed(string $now): int
    {
        $count = 0;

        foreach ($this->rows as &$row) {
            if ($row['status'] === ResizeQueue::FAILED) {
                $row = array_merge($row, ['status' => ResizeQueue::PENDING, 'attempts' => 0, 'error' => '', 'queued' => $now, 'next' => $now, 'token' => null, 'until' => null, 'finished' => null]);
                $count++;
            }
        }

        return $count;
    }

    public function oldestPending(): ?string
    {
        $queued = array_column(array_filter($this->rows, static fn (array $row): bool => $row['status'] === ResizeQueue::PENDING), 'queued');

        return $queued === [] ? null : min($queued);
    }

    public function failed(string $since, string $except): int
    {
        return count(array_filter($this->rows, static fn (array $row): bool => $row['status'] === ResizeQueue::FAILED && $row['finished'] >= $since && $row['error'] !== $except));
    }

    private function id(string $path): ?int
    {
        foreach ($this->rows as $id => $row) {
            if ($row['path'] === $path) {
                return $id;
            }
        }

        return null;
    }
}
