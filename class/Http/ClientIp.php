<?php

    namespace Wonder\Http;

    /**
     * IP del client per throttle e `auth_log`.
     *
     * Senza `APP_TRUST_PROXY` vale `REMOTE_ADDR`. Con il flag attivo vale
     * l'ULTIMA voce di `X-Forwarded-For`, cioè quella aggiunta dal proxy:
     * le voci precedenti le sceglie il client.
     */
    final class ClientIp
    {
        public static function resolve(?array $server = null): string
        {
            $server = $server ?? $_SERVER;
            $remote = trim((string) ($server['REMOTE_ADDR'] ?? ''));

            if (!Cookie::trustsProxy()) {
                return $remote;
            }

            $entries = explode(',', (string) ($server['HTTP_X_FORWARDED_FOR'] ?? ''));
            $last = trim((string) end($entries));

            // Voce mancante o non valida: si ripiega su REMOTE_ADDR.
            if ($last === '' || filter_var($last, FILTER_VALIDATE_IP) === false) {
                return $remote;
            }

            return $last;
        }
    }
