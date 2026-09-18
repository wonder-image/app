<?php

namespace Wonder\App\Scheduler\Contracts;

use Wonder\App\Scheduler\Context;

interface TaskInterface
{
    public function key(): string;
    public function label(): string;
    public function expression(): string;
    public function enabled(): bool;
    public function timeout(): int;
    public function defaultParameters(): array;
    public function validate(array $parameters): array;
    public function run(Context $context): array;
}
