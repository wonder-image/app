<?php

namespace Wonder\App\Scheduler;

use Wonder\App\Scheduler\Contracts\{AlwaysEnabledTask, TaskInterface};

abstract class AbstractTask implements TaskInterface, AlwaysEnabledTask
{
    public function label(): string { return $this->key(); }
    public function expression(): string { return '0 0 * * *'; }
    public function enabled(): bool { return false; }
    public function alwaysEnabled(): bool { return false; }
    public function timeout(): int { return 300; }
    public function defaultParameters(): array { return []; }
    public function validate(array $parameters): array
    {
        if ($parameters !== []) {
            throw new \InvalidArgumentException('Questa attivita non accetta parametri.');
        }
        return [];
    }
}
