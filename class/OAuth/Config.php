<?php

namespace Wonder\OAuth;

use Wonder\App\Credentials;
use Wonder\App\Environment;

/** Valori OAuth modificabili da `.env`, con default e limiti (vedi spec, "Configurazione"). */
final class Config
{
    public const CODE_TTL = 300;
    public const REQUEST_TTL = 600;
    public const MAX_REQUESTS = 10;

    /** chiave => [default, minimo, massimo] */
    private const KEYS = [
        'OAUTH_REGISTER_RATE_LIMIT' => [20, 0, 1000],
        'OAUTH_ACCESS_TOKEN_TTL' => [3600, 300, 86400],
        'OAUTH_REFRESH_TOKEN_TTL' => [2592000, 3600, 7776000],
        'OAUTH_GRANT_MAX_AGE' => [7776000, 86400, 31536000],
    ];

    private static array $warned = [];

    public static function registerRateLimit(): int { return self::int('OAUTH_REGISTER_RATE_LIMIT'); }
    public static function accessTokenTtl(): int { return self::int('OAUTH_ACCESS_TOKEN_TTL'); }
    public static function refreshTokenTtl(): int { return self::int('OAUTH_REFRESH_TOKEN_TTL'); }
    public static function grantMaxAge(): int { return self::int('OAUTH_GRANT_MAX_AGE'); }

    public static function resetWarnings(): void { self::$warned = []; }

    /** Issuer = APP_URL senza slash finale. Mai l'header Host. */
    public static function issuer(): string
    {
        $url = rtrim(trim((string) (self::raw('APP_URL') ?? '')), '/');

        if ($url === '' || (!str_starts_with($url, 'https://') && !Environment::isLocal())) {
            error_log('[oauth] APP_URL deve essere https fuori dall\'ambiente locale: endpoint OAuth disattivati.');
            throw new OAuthException('server_error', 'Authorization server misconfigured.', 500);
        }

        return $url;
    }

    private static function int(string $key): int
    {
        [$default, $min, $max] = self::KEYS[$key];
        $raw = self::raw($key);
        $raw = is_string($raw) ? trim($raw) : $raw;

        if (!is_string($raw) || $raw === '' || preg_match('/^-?\d+$/', $raw) !== 1) {
            return $default;
        }

        $value = (int) $raw;

        // Un valore negativo non è un'intenzione valida: solo 0 esplicito disattiva (dove il minimo è 0).
        if ($value < 0 && $min === 0) {
            return $default;
        }
        $clamped = max($min, min($max, $value));

        if ($clamped !== $value && !isset(self::$warned[$key])) {
            self::$warned[$key] = true;
            error_log("[oauth] {$key}={$value} fuori dai limiti {$min}-{$max}: uso {$clamped}.");
        }

        return $clamped;
    }

    private static function raw(string $key): mixed
    {
        if (class_exists(Credentials::class)) {
            Credentials::loadEnv();
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value === false ? null : $value;
    }
}
