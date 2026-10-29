<?php

namespace Wonder\Api;

use Wonder\Http\HttpErrorResponse;

/**
 * La busta di ogni risposta JSON: `{success, status, response}`.
 *
 * `response` è il dato quando va bene, il messaggio quando va male; `status`
 * è il codice dell'app (l'HTTP lo dà `ErrorStatus::http()`). `request_id` c'è
 * solo quando serve a ritrovare l'errore nel log.
 */
final class Envelope
{
    /** @return array{success:true,status:int,response:mixed} */
    public static function success(mixed $response = [], int $status = 200): array
    {
        return ['success' => true, 'status' => $status, 'response' => $response];
    }

    /** @return array{success:false,status:int,response:string,request_id?:string} */
    public static function error(string $message, int $status, ?string $requestId = null): array
    {
        $payload = [
            'success' => false,
            'status' => $status,
            'response' => $message !== '' ? $message : HttpErrorResponse::GENERIC_MESSAGE,
        ];

        if ($requestId !== null && $requestId !== '') {
            $payload['request_id'] = $requestId;
        }

        return $payload;
    }
}
