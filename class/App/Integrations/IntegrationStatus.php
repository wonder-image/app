<?php

namespace Wonder\App\Integrations;

/** Lo stato di un'integrazione letto dai valori salvati. */
final class IntegrationStatus
{
    public const DISCONNECTED = 'disconnected';
    public const PARTIAL = 'partial';
    public const CONNECTED = 'connected';

    /**
     * @param list<string> $missing campi richiesti ancora vuoti
     * @param list<string> $env campi letti dal `.env`
     */
    public function __construct(
        public readonly string $state,
        public readonly array $missing = [],
        public readonly bool $test = false,
        public readonly ?string $note = null,
        public readonly array $env = [],
        public readonly ?string $blockedBy = null,
    ) {
    }

    public function connected(): bool
    {
        return $this->state === self::CONNECTED;
    }

    public function label(): string
    {
        return match ($this->state) {
            self::CONNECTED => 'Connessa',
            self::PARTIAL => 'Parziale',
            default => 'Da connettere',
        };
    }

    public function variant(): string
    {
        return match ($this->state) {
            self::CONNECTED => 'success',
            self::PARTIAL => 'warning',
            default => 'secondary',
        };
    }

    /** @param list<string> $env */
    public function with(?string $state = null, ?array $env = null, ?string $blockedBy = null): self
    {
        return new self(
            $state ?? $this->state,
            $this->missing,
            $this->test,
            $this->note,
            $env ?? $this->env,
            $blockedBy ?? $this->blockedBy,
        );
    }
}
