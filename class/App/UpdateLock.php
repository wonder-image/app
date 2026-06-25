<?php

namespace Wonder\App;

use mysqli;

class UpdateLock
{
    private mysqli $connection;
    private string $name;
    private bool $locked = false;

    public function __construct(mysqli $connection, string $name = 'wonder_image_app_update')
    {
        $this->connection = $connection;
        $this->name = $name;
    }

    public function acquire(int $timeout = 3): bool
    {
        $timeout = max(0, $timeout);
        $name = $this->connection->real_escape_string($this->name);
        $result = $this->connection->query("SELECT GET_LOCK('{$name}', {$timeout}) AS lock_status");

        if (!$result) {
            return false;
        }

        $row = $result->fetch_assoc();
        $result->free();

        $this->locked = isset($row['lock_status']) && (int) $row['lock_status'] === 1;

        return $this->locked;
    }

    public function release(): bool
    {
        if (!$this->locked) {
            return true;
        }

        $name = $this->connection->real_escape_string($this->name);
        $result = $this->connection->query("SELECT RELEASE_LOCK('{$name}') AS release_status");

        if (!$result) {
            return false;
        }

        $row = $result->fetch_assoc();
        $result->free();

        $released = isset($row['release_status']) && (int) $row['release_status'] === 1;

        if ($released) {
            $this->locked = false;
        }

        return $released;
    }
}
