<?php

    namespace Wonder\Plugin\GeoPlugin;

    /**
     * Paese del visitatore, calcolato al primo uso.
     *
     * La prima chiamata interroga `IPInfo` e salva il codice in
     * `$_SESSION['system_cache']['country']`; le successive lo rileggono da lì.
     * Senza sessione attiva il valore resta in memoria per la richiesta.
     */
    final class Geo
    {
        private static ?string $memo = null;

        /** Codice ISO 3166-1 alpha-2 (es. `IT`); `IT` se il paese non si trova. */
        public static function country(): string
        {

            // Con la sessione vale solo la sessione; il memo serve quando non c'è.
            $cached = isset($_SESSION) ? ($_SESSION['system_cache']['country'] ?? null) : self::$memo;

            if (is_string($cached) && $cached !== '') {
                return $cached;
            }

            $country = (string) (new IPInfo())->Country();
            self::$memo = $country;

            if (isset($_SESSION)) {
                $_SESSION['system_cache']['country'] = $country;
            }

            return $country;

        }

        /** Solo per i test: dimentica il valore in memoria. */
        public static function reset(): void
        {

            self::$memo = null;

        }
    }
