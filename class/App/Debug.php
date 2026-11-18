<?php

namespace Wonder\App;

/**
 * Gate di debug condiviso: unica fonte di verità per il debug.
 *
 * `APP_DEBUG` impostato vince sempre (anche `false`); se manca è acceso solo
 * con `APP_ENV=local`, quindi spento in `staging` e `production`. Se `APP_ENV`
 * manca o non è valido il debug resta spento (senza memorizzarlo): l'errore
 * vero lo lancia `Environment::bootGuard()` all'avvio e il gestore degli errori
 * non deve fallire a sua volta. Nessun fallback su `REMOTE_ADDR`/`SERVER_NAME`: dietro
 * un reverse proxy la richiesta arriva da 127.0.0.1 anche in produzione.
 */
final class Debug
{
    private static ?bool $enabled = null;

    public static function enabled(): bool
    {
        if (self::$enabled !== null) {
            return self::$enabled;
        }

        // Senza il .env caricato una chiamata precoce memorizzerebbe il default.
        if (class_exists(Credentials::class)) {
            Credentials::loadEnv();
        }

        $env = $_ENV['APP_DEBUG'] ?? ($_SERVER['APP_DEBUG'] ?? null);

        if (is_bool($env)) {
            return self::$enabled = $env;
        }

        $envValue = strtolower(trim((string) $env));

        if ($envValue !== '') {
            return self::$enabled = in_array($envValue, ['1', 'true', 'on', 'yes'], true);
        }

        try {
            return self::$enabled = Environment::isLocal();
        } catch (EnvironmentException) {
            return false;
        }
    }

    /** Azzera la memoizzazione (uso nei test). */
    public static function reset(): void
    {
        self::$enabled = null;
    }
}
