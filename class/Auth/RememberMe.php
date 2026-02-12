<?php

    namespace Wonder\Auth;

    /**
     * Gestione Remember Me per sessioni persistenti.
     */
    class RememberMe
    {
        // Durata del remember-me
        public static function ttlSeconds(): int
        {
            return 60 * 60 * 24 * 30; // 30 giorni
        }

        // Prova il login automatico con cookie
        public static function tryLogin(string $area): ?int
        {
            $name = self::cookieName($area);
            if (empty($_COOKIE[$name])) { return null; }

            $parts = explode(':', $_COOKIE[$name], 2);
            $selector = $parts[0] ?? '';
            $validator = $parts[1] ?? '';

            if (empty($selector) || empty($validator)) {
                self::clear($area);
                return null;
            }

            $SQL = sqlSelect('auth_remember', [ 'selector' => $selector, 'deleted' => 'false' ], 1);
            if (!$SQL->exists) {
                self::clear($area);
                return null;
            }

            $row = $SQL->row;

            if (($row['area'] ?? '') !== $area) {
                self::clear($area);
                return null;
            }

            $expiresAt = $row['expires_at'] ?? '';
            if (!empty($expiresAt) && strtotime($expiresAt) < time()) {
                sqlModify('auth_remember', [ 'deleted' => 'true' ], 'id', $row['id']);
                self::clear($area);
                return null;
            }

            $expected = $row['token_hash'] ?? '';
            $actual = hash('sha256', $validator);
            $match = function_exists('hash_equals') ? hash_equals($expected, $actual) : ($expected === $actual);
            if (!$match) {
                sqlModify('auth_remember', [ 'deleted' => 'true' ], 'id', $row['id']);
                self::clear($area);
                return null;
            }

            // Sessione valida: rigenera per sicurezza
            session_regenerate_id(true);
            $_SESSION['user_id'] = (int) $row['user_id'];

            // Rotazione token
            $newValidator = self::randomHex(32, 64);
            $newHash = hash('sha256', $newValidator);
            $newExpires = time() + self::ttlSeconds();

            sqlModify('auth_remember', [
                'token_hash' => $newHash,
                'expires_at' => date('Y-m-d H:i:s', $newExpires),
                'last_used' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ], 'id', $row['id']);

            self::setCookie($area, $selector, $newValidator, $newExpires);

            return (int) $row['user_id'];
        }

        // Salva un token remember-me per l'utente
        public static function set(int $userId, string $area): void
        {
            $selector = self::randomHex(12, 24);
            $validator = self::randomHex(32, 64);
            $tokenHash = hash('sha256', $validator);
            $expiresAt = time() + self::ttlSeconds();

            $values = [
                'user_id' => $userId,
                'selector' => $selector,
                'token_hash' => $tokenHash,
                'area' => $area,
                'expires_at' => date('Y-m-d H:i:s', $expiresAt),
                'last_used' => date('Y-m-d H:i:s'),
                'ip' => $_SERVER['REMOTE_ADDR'] ?? '',
                'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? ''
            ];

            sqlInsert('auth_remember', $values);
            self::setCookie($area, $selector, $validator, $expiresAt);
        }

        // Revoca il token remember-me e rimuove il cookie
        public static function clear(string $area): void
        {
            $name = self::cookieName($area);
            if (!empty($_COOKIE[$name])) {
                $parts = explode(':', $_COOKIE[$name], 2);
                $selector = $parts[0] ?? '';
                if (!empty($selector)) {
                    sqlModify('auth_remember', [ 'deleted' => 'true' ], 'selector', $selector);
                }
            }

            \Wonder\Http\Cookie::clear($name);
        }

        // Nome cookie separato per area (backend/frontend)
        private static function cookieName(string $area): string
        {
            $area = preg_replace('/[^a-z0-9_-]/i', '', strtolower($area));
            return "remember_$area";
        }

        // Imposta cookie remember-me
        private static function setCookie(string $area, string $selector, string $validator, int $expiresAt): void
        {
            $name = self::cookieName($area);
            $value = $selector.':'.$validator;
            \Wonder\Http\Cookie::set($name, $value, $expiresAt);
        }

        // Genera un token esadecimale
        private static function randomHex(int $bytes, int $fallbackLength): string
        {
            if (function_exists('random_bytes')) {
                return bin2hex(random_bytes($bytes));
            }

            return code($fallbackLength, 'all');
        }
    }
