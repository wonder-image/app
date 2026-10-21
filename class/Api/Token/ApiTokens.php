<?php

namespace Wonder\Api\Token;

use Firebase\JWT\JWT;
use Throwable;
use Wonder\App\Credentials;

/**
 * Emissione dei token API (JWT HS256 firmati con `APP_KEY`).
 *
 * - token API: `sub`, `iat`, `jti` casuale e `exp` secondo `API_TOKEN_TTL`;
 *   vale solo finche coincide con `api_users.token` (rotazione = revoca).
 * - token di pagina (`typ = page`): breve, sempre con `exp`, legato alla riga
 *   `api_users` di `@system` (`tid`) e all'impronta del suo token (`tkh`):
 *   rigenerare il token di sistema invalida anche i token di pagina. Con una
 *   sessione PHP attiva porta anche `sid`, HMAC dell'id di sessione: vale
 *   solo con il cookie di quella sessione.
 */
final class ApiTokens
{
    public const PAGE_TYPE = 'page';
    public const FINGERPRINT_PREFIX = 'sha256:';

    public static function newJti(): string
    {
        return bin2hex(random_bytes(16));
    }

    /** @param int|null $ttl secondi; `null` = `API_TOKEN_TTL`, `0` = senza scadenza */
    public static function issue(int|string $userId, ?int $ttl = null, ?string $key = null, ?int $now = null): string
    {
        $now ??= time();
        $ttl ??= TokenConfig::ttl();

        $claims = ['sub' => $userId, 'iat' => $now, 'jti' => self::newJti()];

        if ($ttl > 0) {
            $claims['exp'] = $now + $ttl;
        }

        return JWT::encode($claims, $key ?? Credentials::appKey(), 'HS256');
    }

    /**
     * @param array{id: int|string, user_id: int|string, token: string} $row riga `api_users`
     * @param string|null $sessionId id della sessione PHP a cui legare il token (`sid`)
     */
    public static function page(
        array $row,
        ?int $ttl = null,
        ?string $key = null,
        ?int $now = null,
        ?string $sessionId = null,
    ): string {
        $now ??= time();
        $ttl = max(1, $ttl ?? TokenConfig::pageTtl());
        $key ??= Credentials::appKey();

        $claims = [
            'sub' => $row['user_id'],
            'iat' => $now,
            'exp' => $now + $ttl,
            'jti' => self::newJti(),
            'typ' => self::PAGE_TYPE,
            'tid' => $row['id'],
            'tkh' => self::binding((string) $row['token']),
        ];

        if ($sessionId !== null && $sessionId !== '') {
            $claims['sid'] = self::sessionBinding($sessionId, $key);
        }

        return JWT::encode($claims, $key, 'HS256');
    }

    /** Claim `sid`: HMAC dell'id di sessione, mai l'id in chiaro. */
    public static function sessionBinding(string $sessionId, ?string $key = null): string
    {
        return substr(hash_hmac('sha256', 'page-sid|'.$sessionId, $key ?? Credentials::appKey()), 0, 32);
    }

    /** Id della sessione PHP attiva, o null. */
    public static function currentSessionId(): ?string
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            return null;
        }

        $id = session_id();

        return is_string($id) && $id !== '' ? $id : null;
    }

    /**
     * Token di pagina per `@system` (la riga `api_users` id 1, la stessa di
     * `Credentials::appToken()`), legato alla sessione corrente se attiva;
     * stringa vuota se non disponibile, come `appToken()` prima del primo
     * `forge update`.
     */
    public static function pageToken(): string
    {
        try {
            $row = Credentials::systemApiUser();

            if (($row['token'] ?? '') === '' || empty($row['user_id'])) {
                return '';
            }

            return self::page($row, null, null, null, self::currentSessionId());
        } catch (Throwable $e) {
            error_log('[api] token di pagina non emesso: '.$e->getMessage());

            return '';
        }
    }

    /** Impronta del token salvato, inclusa nei token di pagina. */
    public static function binding(string $token): string
    {
        return substr(hash('sha256', $token), 0, 16);
    }

    /** Riferimento non reversibile al bearer per `api_activity.token`. */
    public static function fingerprint(?string $bearer): string
    {
        $bearer = (string) $bearer;

        return $bearer === '' ? '' : self::FINGERPRINT_PREFIX.substr(hash('sha256', $bearer), 0, 16);
    }
}
