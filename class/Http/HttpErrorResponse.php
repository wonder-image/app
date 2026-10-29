<?php

namespace Wonder\Http;

final class HttpErrorResponse
{
    public const GENERIC_MESSAGE = 'Errore interno.';

    /**
     * Le chiavi `success`, `status`, `response` restano sempre; `request_id`
     * si aggiunge quando c'è (sui 500), per correlare la risposta al log.
     */
    public static function jsonPayload(int $status, string $message = '', ?string $requestId = null): array
    {
        return \Wonder\Api\Envelope::error($message, $status, $requestId);
    }
}
