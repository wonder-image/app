<?php

namespace Wonder\App;

/**
 * Gate di debug condiviso: unica fonte di verità per APP_DEBUG + fallback
 * localhost. Estratto da RouteDispatcher::debugEnabled() per evitare la
 * duplicazione tra dispatcher e diagnostica.
 */
final class Debug
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        $env = $_ENV['APP_DEBUG'] ?? ($_SERVER['APP_DEBUG'] ?? null);

        if (is_bool($env)) {
            return self::$enabled = $env;
        }

        $envValue = strtolower(trim((string) $env));

        if (in_array($envValue, ['1', 'true', 'on', 'yes'], true)) {
            return self::$enabled = true;
        }

        $remoteAddr = trim((string) ($_SERVER['REMOTE_ADDR'] ?? ''));
        $serverName = trim((string) ($_SERVER['SERVER_NAME'] ?? ''));

        return self::$enabled =
            in_array($remoteAddr, ['127.0.0.1', '::1'], true)
            || in_array($serverName, ['127.0.0.1', 'localhost'], true);
    }

    /** Azzera la memoizzazione (uso nei test). */
    public static function reset(): void
    {
        self::$enabled = null;
    }
}
