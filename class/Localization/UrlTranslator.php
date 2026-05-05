<?php

    namespace Wonder\Localization;

    /**
     * Traduce path delle URL fra forma canonical (chiave route) e forma localizzata.
     *
     * Carica file `lang/{locale}/urls.json` da uno o più path registrati. Il merge
     * fra path multipli è "last-wins" sulla stessa chiave, così che un progetto
     * consumer possa override le traduzioni distribuite da un modulo plugin.
     *
     * Convenzione delle chiavi:
     * - chiave = path canonical senza slash iniziale/finale, parametri come
     *   `{name}` letterali. Esempi: `contact`, `products/{slug}`,
     *   `legal/privacy-policy`.
     * - valore = path tradotto, stessi parametri. Esempio:
     *   `"products/{slug}": "prodotti/{slug}"`.
     *
     * Se la traduzione non esiste per una lingua, fallback al path canonical.
     * In ambiente non production (`APP_DEBUG` ≠ `false/0/off`) viene anche
     * loggato un warning via `error_log()`.
     *
     * Esempio di uso:
     *
     * ```php
     * UrlTranslator::addPath($ROOT.'/lang/');
     * UrlTranslator::translate('contact', 'it');             // 'contatti'
     * UrlTranslator::translate('products/{slug}', 'it');     // 'prodotti/{slug}'
     * UrlTranslator::reverseTranslate('contatti', 'it');     // 'contact'
     * ```
     */
    final class UrlTranslator
    {
        /**
         * Directory registrate (assolute, senza slash finale).
         *
         * @var string[]
         */
        private static array $paths = [];

        /**
         * Cache forward (locale => canonical => translated).
         *
         * @var array<string,array<string,string>>
         */
        private static array $forward = [];

        /**
         * Cache reverse (locale => translated => canonical).
         *
         * @var array<string,array<string,string>>
         */
        private static array $reverse = [];

        /**
         * Lingue per cui la cache è già stata costruita.
         *
         * @var array<string,bool>
         */
        private static array $loaded = [];

        /**
         * Registra una directory che contiene `{locale}/urls.json`.
         *
         * Esempio: `addPath('/var/www/site/lang/')` si aspetta che esistano
         *
         *   /var/www/site/lang/it/urls.json
         *   /var/www/site/lang/en/urls.json
         *
         * I file mancanti per una data lingua vengono ignorati senza errore.
         */
        public static function addPath(string $directory): void
        {
            $directory = rtrim($directory, "/\\");

            if ($directory === '' || in_array($directory, self::$paths, true)) {
                return;
            }

            self::$paths[] = $directory;

            // Invalidate caches per forzare il re-merge alla prossima query.
            self::$forward = [];
            self::$reverse = [];
            self::$loaded = [];
        }

        /**
         * Directory registrate, in ordine di registrazione.
         *
         * @return string[]
         */
        public static function paths(): array
        {
            return self::$paths;
        }

        /**
         * Traduce un path canonical nella forma localizzata per `$locale`.
         *
         * Se non c'è traduzione, ritorna il canonical (normalizzato).
         * In non-production logga un warning via `error_log()`.
         */
        public static function translate(string $canonical, string $locale): string
        {
            $key = self::normalizeKey($canonical);

            if ($key === '') {
                return self::normalizeKey($canonical);
            }

            self::ensureLoaded($locale);

            if (isset(self::$forward[$locale][$key])) {
                return self::$forward[$locale][$key];
            }

            if (!self::isProduction()) {
                error_log("[UrlTranslator] missing translation: locale={$locale} key={$key}");
            }

            return $key;
        }

        /**
         * Inverso di translate(): dato un path tradotto, ritorna il canonical.
         *
         * Restituisce null se nessuna mappa contiene quel path tradotto.
         */
        public static function reverseTranslate(string $translatedPath, string $locale): ?string
        {
            $key = self::normalizeKey($translatedPath);

            if ($key === '') {
                return null;
            }

            self::ensureLoaded($locale);

            return self::$reverse[$locale][$key] ?? null;
        }

        /**
         * Mappa forward completa per una lingua (canonical => translated).
         *
         * Utile per espandere le route a load-time (vedi
         * `Wonder\Http\Route::expandTranslatableRoutes()` in F3).
         *
         * @return array<string,string>
         */
        public static function all(string $locale): array
        {
            self::ensureLoaded($locale);
            return self::$forward[$locale] ?? [];
        }

        /**
         * True se esiste una traduzione esplicita per `(canonical, locale)`.
         */
        public static function has(string $canonical, string $locale): bool
        {
            $key = self::normalizeKey($canonical);

            if ($key === '') {
                return false;
            }

            self::ensureLoaded($locale);

            return isset(self::$forward[$locale][$key]);
        }

        /**
         * Reset completo. Per test.
         */
        public static function reset(): void
        {
            self::$paths = [];
            self::$forward = [];
            self::$reverse = [];
            self::$loaded = [];
        }

        /**
         * Normalizza una chiave path: trim slash, collassa slash multipli.
         *
         * Esempi:
         * - `'/contact/'`        → `'contact'`
         * - `'  /products//{slug}/'` → `'products/{slug}'`
         * - `''`                 → `''`
         */
        public static function normalizeKey(string $path): string
        {
            $path = trim($path, "/ \t\n\r\0\x0B");
            $collapsed = preg_replace('#/+#', '/', $path);

            return is_string($collapsed) ? $collapsed : $path;
        }

        private static function ensureLoaded(string $locale): void
        {
            if (isset(self::$loaded[$locale])) {
                return;
            }

            $forward = [];

            foreach (self::$paths as $directory) {
                $file = $directory.'/'.$locale.'/urls.json';

                if (!is_file($file) || !is_readable($file)) {
                    continue;
                }

                $raw = @file_get_contents($file);
                if ($raw === false) {
                    continue;
                }

                $decoded = json_decode($raw, true);
                if (!is_array($decoded)) {
                    if (!self::isProduction()) {
                        error_log("[UrlTranslator] invalid JSON: {$file}");
                    }
                    continue;
                }

                foreach ($decoded as $canonical => $translated) {
                    if (!is_string($canonical) || !is_string($translated)) {
                        continue;
                    }

                    $key = self::normalizeKey($canonical);
                    $value = self::normalizeKey($translated);

                    if ($key === '' || $value === '') {
                        continue;
                    }

                    if (!self::placeholdersMatch($key, $value) && !self::isProduction()) {
                        error_log("[UrlTranslator] placeholder mismatch in {$file}: '{$key}' -> '{$value}'");
                    }

                    // last-wins fra path multipli (es. progetto override su modulo)
                    $forward[$key] = $value;
                }
            }

            self::$forward[$locale] = $forward;
            self::$reverse[$locale] = self::buildReverse($forward, $locale);
            self::$loaded[$locale] = true;
        }

        /**
         * @param array<string,string> $forward
         * @return array<string,string>
         */
        private static function buildReverse(array $forward, string $locale): array
        {
            $reverse = [];

            foreach ($forward as $canonical => $translated) {
                if (isset($reverse[$translated])) {
                    if (!self::isProduction()) {
                        error_log(sprintf(
                            "[UrlTranslator] reverse collision: locale=%s '%s' and '%s' both map to '%s'",
                            $locale,
                            $reverse[$translated],
                            $canonical,
                            $translated,
                        ));
                    }
                    continue;
                }

                $reverse[$translated] = $canonical;
            }

            return $reverse;
        }

        /**
         * I segnaposto (es. `{slug}`) sono gli stessi nelle due stringhe?
         *
         * Confronto come set ordinato: `products/{slug}` e `articoli/{slug}`
         * matchano; `products/{slug}` e `articoli/{altro}` no.
         */
        private static function placeholdersMatch(string $a, string $b): bool
        {
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $a, $aMatches);
            preg_match_all('/\{([a-zA-Z0-9_]+)\}/', $b, $bMatches);

            $aPh = $aMatches[1] ?? [];
            $bPh = $bMatches[1] ?? [];

            sort($aPh);
            sort($bPh);

            return $aPh === $bPh;
        }

        /**
         * Best-effort: APP_DEBUG=false (o 0/off/no) ⇒ produzione.
         *
         * In assenza di segnali consideriamo non-production (loggiamo).
         * Coerente con la regola TASK A.13.3.
         */
        private static function isProduction(): bool
        {
            $debug = strtolower(trim((string) ($_ENV['APP_DEBUG'] ?? getenv('APP_DEBUG') ?: '')));

            return in_array($debug, ['false', '0', 'off', 'no'], true);
        }
    }
