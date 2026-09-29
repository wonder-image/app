<?php

namespace Wonder\Http;

final class HttpErrorResponse
{
    public static function jsonPayload(int $status, string $message = ''): array
    {
        return [
            'success' => false,
            'status' => $status,
            'response' => $message !== '' ? $message : 'Errore interno.',
        ];
    }
}
