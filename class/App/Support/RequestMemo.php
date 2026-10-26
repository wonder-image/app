<?php

namespace Wonder\App\Support;

/**
 * Memo per richiesta: valori statici del processo, quindi vivono una richiesta
 * sotto PHP-FPM. Lo scope di un valore letto da una tabella è il nome della
 * tabella: `Query` lo svuota a ogni Insert/Update/Delete/Truncate su quella
 * tabella e `Transaction` svuota tutto quando annulla.
 * Nei test CLI che riusano il processo chiamare `reset()`.
 * Vedi docs/performance.md.
 */
final class RequestMemo
{
    /** @var array<string, array<string, mixed>> scope => chiave => valore */
    private static array $store = [];

    public static function has(string $scope, string $key): bool
    {
        return isset(self::$store[$scope]) && array_key_exists($key, self::$store[$scope]);
    }

    public static function get(string $scope, string $key, mixed $default = null): mixed
    {
        return self::has($scope, $key) ? self::$store[$scope][$key] : $default;
    }

    public static function put(string $scope, string $key, mixed $value): void
    {
        self::$store[$scope][$key] = $value;
    }

    /**
     * Valore memoizzato o calcolato da `$resolve`.
     *
     * @template T
     * @param callable(): T $resolve
     * @return T
     */
    public static function remember(string $scope, string $key, callable $resolve): mixed
    {
        if (self::has($scope, $key)) {
            return self::$store[$scope][$key];
        }

        return self::$store[$scope][$key] = $resolve();
    }

    /** Dimentica una chiave, o l'intero scope se `$key` è null. */
    public static function forget(string $scope, ?string $key = null): void
    {
        if (!isset(self::$store[$scope])) {
            return;
        }

        if ($key === null) {
            unset(self::$store[$scope]);
            return;
        }

        unset(self::$store[$scope][$key]);
    }

    public static function reset(): void
    {
        self::$store = [];
    }
}
