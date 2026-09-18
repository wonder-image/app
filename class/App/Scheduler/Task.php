<?php

namespace Wonder\App\Scheduler;

/** Small callback adapter; API endpoints and CLI share the same task service. */
class Task extends AbstractTask
{
    private string $title;
    private string $cron = '0 0 * * *';
    private bool $active = false;
    private int $seconds = 300;
    private ?\Closure $validator = null;
    private array $defaults = [];

    protected function __construct(private string $id, private \Closure $callback)
    {
        $this->title = $id;
    }

    public static function make(string $key, callable $callback): static
    {
        return new static($key, \Closure::fromCallable($callback));
    }

    public function named(string $label): static { $this->title = $label; return $this; }
    public function schedule(string $expression): static
    {
        new \Cron\CronExpression($expression);
        $this->cron = $expression;
        return $this;
    }
    public function active(bool $enabled = true): static { $this->active = $enabled; return $this; }
    public function maxSeconds(int $seconds): static
    {
        if ($seconds < 1 || $seconds > 3600) {
            throw new \InvalidArgumentException('Timeout consentito: 1-3600 secondi.');
        }
        $this->seconds = $seconds;
        return $this;
    }
    public function parameters(callable $validator): static
    {
        $this->validator = \Closure::fromCallable($validator);
        return $this;
    }
    public function key(): string { return $this->id; }
    public function withDefaults(array $parameters): static { $this->defaults = $parameters; return $this; }
    public function defaultParameters(): array { return $this->defaults; }
    public function label(): string { return $this->title; }
    public function expression(): string { return $this->cron; }
    public function enabled(): bool { return $this->active; }
    public function timeout(): int { return $this->seconds; }
    public function validate(array $parameters): array
    {
        return $this->validator ? ($this->validator)($parameters) : parent::validate($parameters);
    }
    public function run(Context $context): array { return ($this->callback)($context) ?? []; }
}
