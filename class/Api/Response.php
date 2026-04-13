<?php

namespace Wonder\Api;

class Response
{
    public function __construct(
        public readonly mixed $payload,
        public readonly int $status = 200,
        public readonly bool $raw = false,
    ) {}

    public static function json(mixed $payload, int $status = 200): self
    {
        return new self($payload, $status, false);
    }

    public static function raw(string $payload, int $status = 200): self
    {
        return new self($payload, $status, true);
    }
}
