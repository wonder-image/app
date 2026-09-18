<?php

namespace Wonder\App;

/**
 * Ambiente dell'applicazione letto da `APP_ENV` (`local` o `production`).
 *
 * Se la variabile manca o non è valida vale `production`: nel dubbio le
 * tabelle modificabili solo in locale restano in sola lettura. Il `.env`
 * appartiene al sito (boilerplate); `forge start` scrive `APP_ENV=local`.
 */
final class Environment
{
    public const LOCAL = 'local';
    public const PRODUCTION = 'production';

    private static ?string $current = null;

    public static function current(): string
    {
        if (self::$current !== null) {
            return self::$current;
        }

        // Il valore sta nel .env del sito: senza questo caricamento una
        // richiesta web che chiede l'ambiente presto (es. registrazione delle
        // route) memorizzerebbe "production" anche in locale.
        if (class_exists(Credentials::class)) {
            Credentials::loadEnv();
        }

        $value = $_ENV['APP_ENV'] ?? $_SERVER['APP_ENV'] ?? getenv('APP_ENV');
        $value = is_string($value) ? strtolower(trim($value)) : '';

        return self::$current = in_array($value, [self::LOCAL, self::PRODUCTION], true)
            ? $value
            : self::PRODUCTION;
    }

    public static function isLocal(): bool
    {
        return self::current() === self::LOCAL;
    }

    public static function isProduction(): bool
    {
        return self::current() === self::PRODUCTION;
    }

    /** Azzera la memoizzazione (uso nei test). */
    public static function reset(): void
    {
        self::$current = null;
    }
}
