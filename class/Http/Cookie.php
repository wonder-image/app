<?php

    namespace Wonder\Http;

    use Wonder\App\Credentials;

    /**
     * Helper per gestione cookie.
     *
     * Tutti i cookie del core escono con `SameSite=Lax`, `Secure` quando la
     * richiesta è HTTPS e `HttpOnly` salvo richiesta esplicita.
     */
    class Cookie
    {
        public const SAME_SITE = 'Lax';

        // Flag del .env: fidarsi di `X-Forwarded-Proto` (sito dietro un proxy che termina HTTPS).
        public const TRUST_PROXY_ENV = 'APP_TRUST_PROXY';

        // Imposta un cookie semplice e sicuro
        public static function set(string $name, string $value, int $expiresAt, string $path = '/', bool $httpOnly = true): void
        {
            setcookie($name, $value, self::options($expiresAt, $path, $httpOnly));
        }

        // Cancella un cookie
        public static function clear(string $name, string $path = '/', bool $httpOnly = true): void
        {
            setcookie($name, '', self::options(time() - 3600, $path, $httpOnly));
        }

        /** Opzioni per `setcookie()`. */
        public static function options(int $expiresAt, string $path = '/', bool $httpOnly = true, ?array $server = null): array
        {
            return [
                'expires' => $expiresAt,
                'path' => $path,
                'domain' => '',
                'secure' => self::isSecure($server),
                'httponly' => $httpOnly,
                'samesite' => self::SAME_SITE,
            ];
        }

        /** Opzioni per `session_set_cookie_params()`. */
        public static function sessionOptions(int $lifetime, ?array $server = null): array
        {
            $options = self::options(0, '/', true, $server);
            unset($options['expires']);

            return ['lifetime' => $lifetime] + $options;
        }

        /**
         * HTTPS diretto (`$_SERVER['HTTPS']`); `X-Forwarded-Proto: https`
         * conta solo con `APP_TRUST_PROXY` attivo, perché senza un proxy
         * davanti l'header lo sceglie il client.
         */
        public static function isSecure(?array $server = null): bool
        {
            $server = $server ?? $_SERVER;
            $https = strtolower(trim((string) ($server['HTTPS'] ?? '')));

            if ($https !== '' && $https !== 'off') {
                return true;
            }

            if (!self::trustsProxy()) {
                return false;
            }

            $proto = strtolower(trim(explode(',', (string) ($server['HTTP_X_FORWARDED_PROTO'] ?? ''))[0]));

            return $proto === 'https';
        }

        public static function trustsProxy(): bool
        {
            if (class_exists(Credentials::class)) {
                Credentials::loadEnv();
            }

            $value = $_ENV[self::TRUST_PROXY_ENV] ?? $_SERVER[self::TRUST_PROXY_ENV] ?? getenv(self::TRUST_PROXY_ENV);

            if (is_bool($value)) {
                return $value;
            }

            return in_array(strtolower(trim((string) $value)), ['1', 'true', 'on', 'yes'], true);
        }
    }
