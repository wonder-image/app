<?php

namespace Wonder\App\Support;

use Wonder\Api\Envelope;
use Wonder\Api\ErrorStatus;

final class ApiRequest
{
    /** Corpo già letto: `php://input` si legge una volta per richiesta. */
    private static ?array $body = null;

    public static function input(string $key, mixed $default = null): mixed
    {
        $body = self::body();

        if (array_key_exists($key, $body)) {
            return $body[$key];
        }

        if (array_key_exists($key, $_GET)) {
            return $_GET[$key];
        }

        return $_REQUEST[$key] ?? $default;
    }

    /**
     * Il corpo della richiesta: JSON con `Content-Type: application/json`,
     * altrimenti `$_POST`. Un JSON non valido, o che non è un oggetto, è 400.
     *
     * @return array<string, mixed>
     */
    public static function body(): array
    {
        if (self::$body !== null) {
            return self::$body;
        }

        $type = (string) ($_SERVER['CONTENT_TYPE'] ?? $_SERVER['HTTP_CONTENT_TYPE'] ?? '');
        $body = self::parseBody($type, self::isJson($type) ? (string) file_get_contents('php://input') : '');

        if ($body === null) {
            self::error('Corpo JSON non valido.', 400);
        }

        return self::$body = $body;
    }

    /**
     * @return array<string, mixed>|null null se il JSON non è valido
     */
    public static function parseBody(string $contentType, string $raw): ?array
    {
        if (!self::isJson($contentType)) {
            return is_array($_POST) ? $_POST : [];
        }

        if (trim($raw) === '') {
            return [];
        }

        $data = json_decode($raw, true);

        // Solo oggetti: una lista o uno scalare non hanno chiavi da leggere.
        if (!is_array($data) || ($data !== [] && array_is_list($data))) {
            return null;
        }

        return $data;
    }

    private static function isJson(string $contentType): bool
    {
        $type = strtolower(trim(explode(';', $contentType)[0]));

        return $type === 'application/json' || str_ends_with($type, '+json');
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

    /**
     * Connessione scelta dal client: un alias configurato in `$DB->database`
     * o il nome reale di uno di quei database (mai `information_schema`),
     * altrimenti 422.
     *
     * @param list<string>|null $allowed alias ammessi; default da `$DB->database`
     */
    public static function selectDatabase(?string $database = null, ?array $allowed = null): string
    {
        $database = trim((string) ($database ?? 'main'));

        if ($database === '') {
            $database = 'main';
        }

        // Veto sul nome ricevuto: un alias potrebbe mappare lo schema di sistema.
        if (strtolower($database) === 'information_schema') {
            self::error('Parametri non validi.', 422);
        }

        // Le liste del backend inviano il nome reale (Table::$database): si risale all'alias.
        $database = self::databaseAlias($database);

        if ($database === 'main') {
            return $database;
        }

        $allowed ??= self::configuredDatabases();

        if (strtolower($database) === 'information_schema' || !in_array($database, $allowed, true)) {
            self::error('Parametri non validi.', 422);
        }

        sqlDatabase($database);

        return $database;
    }

    private static function databaseAlias(string $database): string
    {
        $databases = $GLOBALS['DB']->database ?? [];

        if (!is_array($databases) || array_key_exists($database, $databases)) {
            return $database;
        }

        $alias = array_search($database, array_map('strval', $databases), true);

        return $alias === false ? $database : (string) $alias;
    }

    /** @return list<string> */
    private static function configuredDatabases(): array
    {
        $databases = $GLOBALS['DB']->database ?? [];

        return is_array($databases) ? array_values(array_map('strval', array_keys($databases))) : [];
    }

    public static function json(array $payload, int $status = 200): never
    {
        http_response_code($status);
        header('Content-Type: application/json; charset=utf-8');

        echo json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        exit;
    }

    /** Messaggio più chiavi in cima: lo usa ancora `/api/token/page/`; il nuovo codice usa `ok()`. */
    public static function success(string $message = 'ok', array $extra = []): never
    {
        self::json(array_merge([
            'success' => true,
            'status' => 200,
            'response' => $message,
        ], $extra), 200);
    }

    /** Il dato nella busta comune: `{success: true, status, response}`. */
    public static function ok(array|string $response = [], int $status = 200): never
    {
        self::json(Envelope::success($response, $status), $status);
    }

    public static function error(string $message, int $status = 400): never
    {
        self::json(Envelope::error($message, $status), ErrorStatus::http($status));
    }
}
