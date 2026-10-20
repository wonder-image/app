<?php

    namespace Wonder\Auth;

    use Wonder\App\Environment;

    /**
     * Limita i tentativi di login e recupero password contando le righe di
     * `auth_log` in una finestra scorrevole (nessuna tabella dedicata).
     */
    final class LoginThrottle
    {
        public const ALERT = 930;

        /** Eventi contati e limiti [tentativi, secondi] per bucket. */
        private const BUCKETS = [
            'login' => [
                'events' => ['login_failed'],
                'identifier' => [5, 900],
                'ip' => [20, 900],
            ],
            'recovery' => [
                'events' => ['recovery_request', 'password_recovery', 'recovery_unknown'],
                'identifier' => [3, 900],
                'ip' => [10, 3600],
            ],
        ];

        /** @var callable|null Contatore sostituibile nei test: fn(string $where): int */
        private static $counter = null;

        private static int $retryAfter = 0;

        /** True se il tentativo può procedere. */
        public static function check(string $bucket, string $identifier, string $ip): bool
        {
            if (!isset(self::BUCKETS[$bucket])) {
                throw new \InvalidArgumentException("Bucket di throttle sconosciuto: {$bucket}");
            }

            self::$retryAfter = 0;
            $config = self::BUCKETS[$bucket];
            $identifier = self::identifier($identifier);
            $ip = trim($ip);

            $checks = [];
            if ($identifier !== '') {
                $checks[] = ['identifier', $identifier];
            }
            if ($ip !== '') {
                $checks[] = ['ip', $ip];
            }

            foreach ($checks as [$scope, $value]) {
                [$limit, $window] = self::limit($bucket, $scope);
                $count = self::count(self::condition($config['events'], $scope, $value, $window));

                if ($count >= $limit) {
                    self::$retryAfter = $window;
                    return false;
                }
            }

            return true;
        }

        /** Secondi da indicare in `Retry-After` dopo un `check()` negativo. */
        public static function retryAfter(): int
        {
            return self::$retryAfter;
        }

        /** Invia `Retry-After` se gli header sono ancora modificabili. */
        public static function sendRetryAfter(): void
        {
            if (self::$retryAfter > 0 && !headers_sent()) {
                header('Retry-After: '.self::$retryAfter);
            }
        }

        /** Forma normalizzata salvata in `meta.identifier`. */
        public static function identifier(string $value): string
        {
            return strtolower(trim($value));
        }

        /** @return array{0:int,1:int} [tentativi, secondi]; in locale i tentativi x10. */
        public static function limit(string $bucket, string $scope): array
        {
            [$limit, $window] = self::BUCKETS[$bucket][$scope];

            return [Environment::isLocal() ? $limit * 10 : $limit, $window];
        }

        /** Condizione SQL su `auth_log` (finestra sull'orologio del DB). */
        public static function condition(array $events, string $scope, string $value, int $window): string
        {
            $placeholders = implode(', ', array_fill(0, count($events), '?'));
            $field = $scope === 'ip'
                ? '`ip`'
                : "JSON_UNQUOTE(JSON_EXTRACT(`meta`, '$.identifier'))";

            return sqlBind(
                "`event` IN ({$placeholders}) AND `creation` >= DATE_SUB(NOW(), INTERVAL ".(int) $window." SECOND) AND {$field} = ?",
                array_merge(array_values($events), [$value])
            );
        }

        /** Solo test: null ripristina il conteggio su `auth_log`. */
        public static function useCounter(?callable $counter): void
        {
            self::$counter = $counter;
        }

        private static function count(string $where): int
        {
            if (self::$counter !== null) {
                return (int) (self::$counter)($where);
            }

            return (int) sqlCount('auth_log', $where);
        }
    }
