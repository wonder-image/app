<?php

    namespace Wonder\Http;

    /**
     * Helper per gestione cookie.
     */
    class Cookie
    {
        // Imposta un cookie semplice e sicuro
        public static function set(string $name, string $value, int $expiresAt, string $path = '/'): void
        {
            $secure = self::isSecure();
            setcookie($name, $value, $expiresAt, $path, '', $secure, true);
        }

        // Cancella un cookie
        public static function clear(string $name, string $path = '/'): void
        {
            $secure = self::isSecure();
            setcookie($name, '', time() - 3600, $path, '', $secure, true);
        }

        // Ritorna se la connessione è HTTPS
        public static function isSecure(): bool
        {
            return (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
        }
    }
