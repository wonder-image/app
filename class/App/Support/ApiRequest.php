<?php

namespace Wonder\App\Support;

final class ApiRequest
{
    public static function input(string $key, mixed $default = null): mixed
    {
        if (array_key_exists($key, $_POST)) {
            return $_POST[$key];
        }

        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $_REQUEST[$key] ?? $default;
    }

    public static function string(string $key, string $default = ''): string
    {
        $value = self::input($key, $default);

        if (!is_scalar($value)) {
            return $default;
        }

        return trim((string) $value);
    }

    public static function int(string $key, int $default = 0): int
    {
        $value = self::input($key, $default);

        if (!is_numeric($value)) {
            return $default;
        }

        return (int) $value;
    }

    public static function isPost(): bool
    {
        return strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST';
    }

    public static function selectDatabase(?string $database = null): string
    {
        $database = trim((string) ($database ?? 'main'));

        if ($database === '') {
            $database = 'main';
        }

        if ($database !== 'main') {
            sqlDatabase($database);
        }

        return $database;
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    public static function success(string $message = 'ok', array $extra = []): never
    {
        self::json(array_merge([
            'success' => true,
            'status' => 200,
            'response' => $message,
        ], $extra), 200);
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json([
            'success' => false,
            'status' => $status,
            'response' => $message,
        ], $status);
    }
}
