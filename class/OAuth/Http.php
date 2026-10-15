<?php

namespace Wonder\OAuth;

/** Colla HTTP condivisa dagli handler OAuth. */
final class Http
{
    public static function noStore(): void
    {
        if (!headers_sent()) {
            header('Cache-Control: no-store');
            header('Pragma: no-cache');
        }
    }

    public static function json(array $payload, int $status = 200): void
    {
        if (!headers_sent()) {
            http_response_code($status);
            self::noStore();
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode($payload, JSON_UNESCAPED_SLASHES);
    }

    /** Body JSON (register) o form-urlencoded (token, revoke). */
    public static function body(): array
    {
        $type = strtolower((string) ($_SERVER['CONTENT_TYPE'] ?? ''));

        if (str_contains($type, 'application/json')) {
            $data = json_decode((string) file_get_contents('php://input'), true);
            return is_array($data) ? $data : [];
        }

        return is_array($_POST) ? $_POST : [];
    }

    public static function ip(): string
    {
        return (string) ($_SERVER['REMOTE_ADDR'] ?? '');
    }

    /** Pagine HTML di login/consenso: niente iframe (clickjacking). */
    public static function htmlPageHeaders(): void
    {
        if (!headers_sent()) {
            self::noStore();
            header('X-Frame-Options: DENY');
            header("Content-Security-Policy: frame-ancestors 'none'");
        }
    }
}
