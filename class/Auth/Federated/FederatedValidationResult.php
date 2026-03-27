<?php

namespace Wonder\Auth\Federated;

final class FederatedValidationResult
{
    public bool $valid;
    public array $errors;

    private function __construct(bool $valid, array $errors = [])
    {
        $this->valid = $valid;
        $this->errors = $errors;
    }

    public static function ok(): self
    {
        return new self(true, []);
    }

    public static function fail(array $errors): self
    {
        return new self(false, array_values($errors));
    }
}
