<?php

namespace Wonder\Api\Token;

use Wonder\App\Credentials;

/**
 * Politica dei token API letta dal `.env`, con default e limiti.
 *
 * - `API_TOKEN_TTL`: durata in secondi dei token emessi da `apiUser()`;
 *   default `0` = nessuna scadenza, perche gli utenti API dei moduli (cron,
 *   push di terzi) non sanno rinnovare il token. I token gia emessi senza
 *   `exp` restano validi finche non vengono rigenerati, salvo
 *   `API_TOKEN_REQUIRE_EXP=true`.
 * - `API_PAGE_TOKEN_TTL`: durata del token di pagina stampato in `API_TOKEN`.
 * - `API_TOKEN_REQUIRE_EXP`: rifiuta i token senza `exp` (opt-in esplicito).
 */
final class TokenConfig
{
    /** chiave => [default, minimo, massimo] */
    private const KEYS = [
        'API_TOKEN_TTL' => [0, 0, 157680000],
        'API_PAGE_TOKEN_TTL' => [43200, 300, 604800],
    ];

    private static array $warned = [];

    public static function ttl(): int { return self::int('API_TOKEN_TTL'); }
    public static function pageTtl(): int { return self::int('API_PAGE_TOKEN_TTL'); }

    public static function requireExp(): bool
    {
        $raw = self::raw('API_TOKEN_REQUIRE_EXP');

        return is_string($raw) && in_array(strtolower(trim($raw)), ['1', 'true', 'yes', 'on'], true);
    }

    public static function resetWarnings(): void { self::$warned = []; }

    private static function int(string $key): int
    {
        [$default, $min, $max] = self::KEYS[$key];
        $raw = self::raw($key);
        $raw = is_string($raw) ? trim($raw) : $raw;

        if (!is_string($raw) || preg_match('/^\d+$/', $raw) !== 1) {
            return $default;
        }

        $value = (int) $raw;
        $clamped = max($min, min($max, $value));

        if ($clamped !== $value && !isset(self::$warned[$key])) {
            self::$warned[$key] = true;
            error_log("[api] {$key}={$value} fuori dai limiti {$min}-{$max}: uso {$clamped}.");
        }

        return $clamped;
    }

    private static function raw(string $key): mixed
    {
        try {
            Credentials::loadEnv();
        } catch (\Throwable) {
            // Senza .env valgono i default.
        }

        $value = $_ENV[$key] ?? $_SERVER[$key] ?? getenv($key);

        return $value === false ? null : $value;
    }
}
