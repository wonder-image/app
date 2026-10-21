<?php

namespace Wonder\Http;

/**
 * Header di sicurezza delle risposte PHP, emessi dal bootstrap del core così
 * valgono anche su nginx/Herd (dove `.htaccess` è ignorato). Per gli asset
 * statici li imposta il blocco `WONDER SECURITY` di `.htaccess`.
 */
final class SecurityHeaders
{
    public const HSTS_ENV = 'APP_HSTS';

    /**
     * Header da emettere, nome => valore.
     *
     * @return array<string, string>
     */
    public static function headers(bool $backend, bool $authenticated, bool $https, bool $hsts): array
    {
        $headers = [
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
            'Content-Security-Policy' => "frame-ancestors 'self'",
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
            // geolocation=(self): la navigazione di GoogleMap usa watchPosition().
            'Permissions-Policy' => 'camera=(), microphone=(), geolocation=(self)',
        ];

        if ($hsts && $https) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000';
        }

        // Le pagine anonime restano cacheabili: il token di pagina è legato al sid.
        if ($backend || $authenticated) {
            $headers['Cache-Control'] = 'no-store, private';
        }

        return $headers;
    }

    /**
     * Emette gli header senza sovrascrivere quelli già impostati da chi è
     * passato prima (es. OAuth con `X-Frame-Options: DENY`). Cache-Control
     * viene sempre sostituito: `session_start()` ne emette uno meno stretto.
     */
    public static function apply(bool $backend, bool $authenticated): void
    {
        header_remove('X-Powered-By');

        if (PHP_SAPI === 'cli' || headers_sent()) {
            return;
        }

        $present = self::presentNames(headers_list());

        foreach (self::headers($backend, $authenticated, Cookie::isSecure(), self::hstsEnabled()) as $name => $value) {
            if ($name !== 'Cache-Control' && isset($present[strtolower($name)])) {
                continue;
            }
            header($name.': '.$value, true);
        }
    }

    /**
     * Nomi (minuscoli) degli header già presenti.
     *
     * @param list<string> $list
     * @return array<string, true>
     */
    public static function presentNames(array $list): array
    {
        $names = [];
        foreach ($list as $line) {
            $name = strtolower(trim(strstr((string) $line, ':', true) ?: ''));
            if ($name !== '') {
                $names[$name] = true;
            }
        }

        return $names;
    }

    public static function hstsEnabled(): bool
    {
        $value = $_ENV[self::HSTS_ENV] ?? $_SERVER[self::HSTS_ENV] ?? getenv(self::HSTS_ENV);

        if (is_bool($value)) {
            return $value;
        }

        return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
    }
}
