<?php

namespace Wonder\App\Support;

use mysqli;

/**
 * Lock nominali MySQL (`GET_LOCK` / `RELEASE_LOCK`), come `UpdateLock`.
 */
final class MysqliNamedLockConnection implements NamedLockConnection
{
    public function __construct(private readonly mysqli $mysqli)
    {
    }

    public function acquire(string $name, int $timeout): bool
    {
        $escaped = $this->mysqli->real_escape_string($name);
        $result = $this->mysqli->query("SELECT GET_LOCK('{$escaped}', ".max(0, $timeout).") AS acquired");

        if (!$result) {
            return false;
        }

        $row = $result->fetch_assoc();

        return (string) ($row['acquired'] ?? '0') === '1';
    }

    public function release(string $name): void
    {
        $escaped = $this->mysqli->real_escape_string($name);
        $this->mysqli->query("SELECT RELEASE_LOCK('{$escaped}')");
    }
}
