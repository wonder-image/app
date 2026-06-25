<?php

    namespace Wonder\App;

    /**
     * Promozione bidirezionale di alias delle variabili d'ambiente per retrocompatibilità.
     *
     * Il framework legge storicamente i nomi "Laravel-style":
     *   DB_HOSTNAME, DB_USERNAME, DB_DATABASE
     *
     * I .env più recenti (Bitwarden, deploy GitHub Actions, convenzioni di hosting)
     * adottano invece i nomi più convenzionali:
     *   DB_HOST, DB_USER, DB_NAME
     *
     * Questo helper risolve il mismatch al boot, copiando i valori in entrambe
     * le direzioni dopo `Dotenv::safeLoad()`. Il resto del framework continua a
     * leggere `$_ENV['DB_HOSTNAME']` etc. senza modifiche.
     *
     * Comportamento:
     * - se solo i nuovi sono popolati → vengono copiati nei vecchi
     * - se solo i vecchi sono popolati → vengono copiati nei nuovi
     * - se entrambi sono popolati e diversi → vince il nuovo, sovrascrive il vecchio
     *
     * `DB_PASSWORD` ha lo stesso nome in entrambi i set: nessun alias necessario.
     *
     * Uso tipico (idempotente, sicuro chiamarla più volte):
     *
     * ```php
     * use Wonder\App\EnvCompat;
     *
     * $dotenv = Dotenv\Dotenv::createImmutable($ROOT);
     * $dotenv->safeLoad();
     * EnvCompat::apply();
     * ```
     */
    final class EnvCompat
    {
        /** @var array<string,string> moderno => legacy */
        private const ALIASES = [
            'DB_HOST' => 'DB_HOSTNAME',
            'DB_USER' => 'DB_USERNAME',
            'DB_NAME' => 'DB_DATABASE',
        ];

        private static bool $applied = false;

        /**
         * Applica le promozioni alias.
         *
         * Idempotente: chiamate successive sono no-op (a meno di reset()).
         */
        public static function apply(): void
        {
            if (self::$applied) {
                return;
            }

            foreach (self::ALIASES as $modern => $legacy) {
                $modernValue = self::read($modern);
                $legacyValue = self::read($legacy);

                if ($modernValue !== '' && $legacyValue === '') {
                    // Solo nuovo presente → propaga al vecchio
                    self::write($legacy, $modernValue);
                } elseif ($legacyValue !== '' && $modernValue === '') {
                    // Solo vecchio presente → propaga al nuovo
                    self::write($modern, $legacyValue);
                } elseif ($modernValue !== '' && $legacyValue !== '' && $modernValue !== $legacyValue) {
                    // Entrambi presenti e divergenti → vince il nuovo
                    self::write($legacy, $modernValue);
                }
            }

            self::$applied = true;
        }

        /**
         * Reset dello stato "già applicato". Utile in test.
         */
        public static function reset(): void
        {
            self::$applied = false;
        }

        /**
         * @internal
         */
        public static function isApplied(): bool
        {
            return self::$applied;
        }

        private static function read(string $key): string
        {
            if (array_key_exists($key, $_ENV)) {
                return trim((string) $_ENV[$key]);
            }

            $fromGetenv = getenv($key);
            if ($fromGetenv === false) {
                return '';
            }

            return trim((string) $fromGetenv);
        }

        private static function write(string $key, string $value): void
        {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv($key.'='.$value);
        }
    }
