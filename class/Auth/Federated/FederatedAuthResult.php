<?php

namespace Wonder\Auth\Federated;

final class FederatedAuthResult
{
    public bool $success;
    public string $status;
    public string $reason;
    public ?int $userId;
    public string $redirectUrl;
    public array $meta;

    private function __construct(
        bool $success,
        string $status,
        string $reason = '',
        ?int $userId = null,
        string $redirectUrl = '',
        array $meta = []
    ) {
        $this->success = $success;
        $this->status = $status;
        $this->reason = $reason;
        $this->userId = $userId;
        $this->redirectUrl = $redirectUrl;
        $this->meta = $meta;
    }

    public static function success(string $status, int $userId, array $meta = []): self
    {
        return new self(true, $status, '', $userId, '', $meta);
    }

    public static function blocked(string $status, string $reason, ?int $userId = null, string $redirectUrl = '', array $meta = []): self
    {
        return new self(false, $status, $reason, $userId, $redirectUrl, $meta);
    }
}
