<?php

namespace Wonder\OAuth;

use RuntimeException;

/** Errore OAuth nel formato RFC 6749: `error` + `error_description`, mai dettagli interni. */
final class OAuthException extends RuntimeException
{
    public function __construct(
        public readonly string $error,
        public readonly string $description = '',
        public readonly int $status = 400,
        public readonly array $headers = [],
        public readonly ?string $redirectUri = null,
        public readonly ?string $state = null,
    ) {
        parent::__construct($description !== '' ? $description : $error);
    }

    public function toArray(): array
    {
        return array_filter([
            'error' => $this->error,
            'error_description' => $this->description,
        ], static fn ($v) => $v !== '');
    }

    public function send(): void
    {
        if (!headers_sent()) {
            http_response_code($this->status);
            Http::noStore();
            header('Content-Type: application/json; charset=utf-8');
            foreach ($this->headers as $name => $value) {
                header($name.': '.$value);
            }
        }
        echo json_encode($this->toArray(), JSON_UNESCAPED_SLASHES);
    }
}
