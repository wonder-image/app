<?php

namespace Wonder\App\Support;

interface NamedLockConnection
{
    public function acquire(string $name, int $timeout): bool;

    public function release(string $name): void;
}
