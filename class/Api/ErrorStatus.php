<?php

namespace Wonder\Api;

/**
 * Codice applicativo → codice HTTP della risposta. I codici dell'app fuori
 * dagli HTTP (617 reCAPTCHA fallito, 618 casella richiesta) escono 422; il
 * codice originale resta nel corpo (`status`).
 */
final class ErrorStatus
{
    public static function http(int $code): int
    {
        if ($code === 617 || $code === 618) {
            return 422;
        }

        return $code >= 400 && $code <= 599 ? $code : 500;
    }
}
