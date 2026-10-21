<?php

namespace Wonder\Http;

/**
 * Identificativo della richiesta, condiviso fra risposta e log. Un valore
 * ricevuto dal client (`X-Request-Id`) vale solo se rispetta il formato.
 */
final class RequestId
{
    public const PATTERN = '/^[A-Za-z0-9._-]{1,64}$/';

    public static function sanitize(mixed $value): ?string
    {
        $value = is_string($value) ? trim($value) : '';

        return preg_match(self::PATTERN, $value) === 1 ? $value : null;
    }

    public static function generate(): string
    {
        try {
            return bin2hex(random_bytes(8));
        } catch (\Throwable) {
            return str_replace('.', '', uniqid('req', true));
        }
    }

    /** Id della richiesta corrente: ricevuto e valido, altrimenti generato e memorizzato. */
    public static function current(): string
    {
        $id = self::sanitize($_SERVER['REQUEST_ID'] ?? null)
            ?? self::sanitize($_SERVER['HTTP_X_REQUEST_ID'] ?? null)
            ?? self::generate();
        $_SERVER['REQUEST_ID'] = $id;

        return $id;
    }

    /** Trace id: ricevuto e valido, altrimenti uguale al request id. */
    public static function trace(): string
    {
        $id = self::sanitize($_SERVER['TRACE_ID'] ?? null)
            ?? self::sanitize($_SERVER['HTTP_TRACE_ID'] ?? null)
            ?? self::current();
        $_SERVER['TRACE_ID'] = $id;

        return $id;
    }
}
