<?php

namespace Wonder\Http\Exceptions;

use RuntimeException;

class HttpException extends RuntimeException
{
    public function __construct(
        private readonly int $statusCode,
        private readonly string $publicMessage = '',
    ) {
        parent::__construct($publicMessage, $statusCode);
    }

    public function statusCode(): int
    {
        return $this->statusCode;
    }

    public function publicMessage(): string
    {
        return $this->publicMessage;
    }
}
