<?php

    namespace Wonder\Http;

    /**
     * IP del client per throttle e `auth_log`.
     *
     * Senza proxy fidato vale `REMOTE_ADDR`. Con `APP_TRUSTED_PROXIES` si
     * scorre `X-Forwarded-For` da destra saltando i proxy della lista: la
     * prima voce che non lo è è il client. Con il solo `APP_TRUST_PROXY` vale
     * l'ULTIMA voce, cioè quella aggiunta dal proxy: le precedenti le sceglie
     * il client. Voce mancante o non valida: `REMOTE_ADDR`.
     */
    final class ClientIp
    {
        public static function resolve(?array $server = null): string
        {
            $server = $server ?? $_SERVER;
            $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));

            if (!TrustedProxies::trusts($server)) {
                return $remote;
            }

            $entries = array_map('trim', explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? '')));
            $list = TrustedProxies::list();

            if ($list === []) {
                return self::valid((string) end($entries)) ? (string) end($entries) : $remote;
            }

            foreach (array_reverse($entries) as $entry) {
                if (!self::valid($entry)) {
                    return $remote;
                }
                if (!TrustedProxies::contains($entry, $list)) {
                    return $entry;
                }
            }

            return $remote;
        }

        private static function valid(string $ip): bool
        {
            return $ip !== '' && filter_var($ip, FILTER_VALIDATE_IP) !== false;
        }
    }
