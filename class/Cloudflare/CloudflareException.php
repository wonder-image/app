<?php

namespace Wonder\Cloudflare;

use RuntimeException;

class CloudflareException extends RuntimeException
{
    /**
     * @param string[] $errors messaggi restituiti da Cloudflare
     */
    public function __construct(
        public readonly int $status,
        public readonly array $errors,
        string $request
    ) {
        $detail = $errors !== [] ? ': '.implode('; ', $errors) : '';

        parent::__construct($request.' → HTTP '.$status.$detail);
    }

    /** Token valido ma senza il permesso richiesto. */
    public function isPermissionError(): bool
    {
        return $this->status === 403;
    }

    public function isNotFound(): bool
    {
        return $this->status === 404;
    }
}
