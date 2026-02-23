<?php

namespace Wonder\App;

use DateTimeImmutable;
use DateTimeInterface;
use Throwable;

/**
 * Logger applicativo minimale.
 * Scrive una riga JSON su file e, in debug, mostra un output testuale semplice.
 */
class Logger
{
    private const DEFAULT_LEVEL = 'ERROR';
    private const MAX_STRING_LENGTH = 1500;

    public static function log(
        Throwable $exception,
        string $service,
        string $action,
        string $level = 'ERROR',
        string $file = 'error',
        array $context = []
    ): void {
        $normalizedLevel = self::normalizeLevel($level);
        $path = self::relativePath((string) $exception->getFile());

        $entry = [
            'ts' => (new DateTimeImmutable('now'))->format(DateTimeInterface::ATOM),
            'level' => $normalizedLevel,
            'service' => $service,
            'action' => $action,
            'message' => self::message($exception),
            'code' => (string) $exception->getCode(),
            'exception' => get_class($exception),
            'file' => basename($path),
            'path' => $path,
            'line' => (int) $exception->getLine(),
            'request_id' => (string) ($_SERVER['HTTP_X_REQUEST_ID'] ?? ($_SERVER['REQUEST_ID'] ?? '-')),
            'trace_id' => (string) ($_SERVER['HTTP_TRACE_ID'] ?? ($_SERVER['TRACE_ID'] ?? '-')),
            'context' => self::normalizeContext($context),
        ];

        $logPath = self::buildLogPath($file);
        self::ensureDirectory($logPath);

        $encoded = json_encode($entry, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($encoded === false) {
            $encoded = json_encode([
                'ts' => $entry['ts'],
                'level' => $entry['level'],
                'service' => $entry['service'],
                'action' => $entry['action'],
                'message' => '[logger encoding error]',
            ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        }

        if (is_string($encoded)) {
            error_log($encoded . PHP_EOL, 3, $logPath);
        }

        if (self::isDebug()) {
            self::renderDebug($entry, $exception);
            exit();
        }
    }

    private static function message(Throwable $exception): string
    {
        $message = trim($exception->getMessage());
        return $message !== '' ? $message : '[messaggio non disponibile]';
    }

    private static function buildLogPath(string $file): string
    {
        $root = defined('ROOT') ? (string) ROOT : (getcwd() ?: '.');
        return rtrim($root, '/') . '/' . ltrim($file, '/') . '.log';
    }

    private static function ensureDirectory(string $logPath): void
    {
        $dir = dirname($logPath);
        if (is_dir($dir)) {
            return;
        }

        @mkdir($dir, 0777, true);
    }

    private static function normalizeLevel(string $level): string
    {
        $level = strtoupper(trim($level));
        return $level !== '' ? $level : self::DEFAULT_LEVEL;
    }

    private static function normalizeContext(array $context): array
    {
        $normalized = [];

        foreach ($context as $key => $value) {
            if (!is_string($key) || $key === '') {
                continue;
            }

            $normalized[$key] = self::normalizeValue($value);
        }

        return $normalized;
    }

    private static function normalizeValue(mixed $value): mixed
    {
        if ($value === null || is_bool($value) || is_int($value) || is_float($value)) {
            return $value;
        }

        if (is_string($value)) {
            return self::truncate($value);
        }

        if (is_array($value)) {
            $normalized = [];
            foreach ($value as $k => $v) {
                $normalized[(string) $k] = self::normalizeValue($v);
            }
            return $normalized;
        }

        $encoded = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if (is_string($encoded)) {
            return self::truncate($encoded);
        }

        return '[non serializzabile]';
    }

    private static function truncate(string $value): string
    {
        if (strlen($value) <= self::MAX_STRING_LENGTH) {
            return $value;
        }

        return substr($value, 0, self::MAX_STRING_LENGTH) . '...';
    }

    private static function relativePath(string $path): string
    {
        if ($path === '' || $path === '-') {
            return '-';
        }

        if (defined('ROOT')) {
            $root = rtrim((string) ROOT, '/');
            if ($root !== '' && str_starts_with($path, $root)) {
                return substr($path, strlen($root));
            }
        }

        return $path;
    }

    private static function isDebug(): bool
    {
        $value = $_ENV['APP_DEBUG'] ?? ($_SERVER['APP_DEBUG'] ?? null);
        if (is_bool($value)) {
            return $value;
        }

        $value = strtolower(trim((string) $value));
        return in_array($value, ['1', 'true', 'on', 'yes'], true);
    }

    private static function renderDebug(array $entry, Throwable $exception): void
    {
        if (!headers_sent()) {
            header('Content-Type: text/plain; charset=UTF-8');
        }

        echo '--- DEBUG LOGGER ---' . PHP_EOL;
        echo '[' . $entry['level'] . '] ' . $entry['service'] . '::' . $entry['action'] . PHP_EOL;
        echo 'Messaggio: ' . $entry['message'] . PHP_EOL;
        echo 'Eccezione: ' . $entry['exception'] . ' (#' . $entry['code'] . ')' . PHP_EOL;
        echo 'Posizione: ' . $entry['path'] . ':' . $entry['line'] . PHP_EOL;
        echo 'Data: ' . $entry['ts'] . PHP_EOL;
        echo 'Request ID: ' . $entry['request_id'] . PHP_EOL;
        echo 'Trace ID: ' . $entry['trace_id'] . PHP_EOL;
        echo PHP_EOL . 'Context:' . PHP_EOL;
        echo self::stringify($entry['context']) . PHP_EOL;
        echo PHP_EOL . 'Trace:' . PHP_EOL;
        echo $exception->getTraceAsString() . PHP_EOL;
    }

    private static function stringify(mixed $value): string
    {
        $json = json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
        if (is_string($json)) {
            return $json;
        }

        return var_export($value, true);
    }
}
