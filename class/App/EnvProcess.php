<?php

    namespace Wonder\App;

    /**
     * Porta in `$_ENV` le variabili d'ambiente reali del processo.
     *
     * Il framework legge i valori da `$_ENV[...]`. Con `variables_order` senza
     * `E` (php.ini-production) o con PHP-FPM e `clear_env`, `$_ENV` resta vuoto
     * anche quando il processo ha le variabili (per esempio avviato con
     * `bws run`, o con `fastcgi_param` / `env[...]`): senza questo passo i
     * segreti passati dall'ambiente non verrebbero visti e il `.env` sarebbe
     * l'unica strada.
     *
     * Regole:
     * - si chiama PRIMA di `Dotenv::safeLoad()`: Dotenv è immutabile e non
     *   sovrascrive ciò che è già in `$_ENV`/`$_SERVER`, quindi l'ambiente del
     *   processo vince sul `.env`;
     * - si copiano solo i nomi di un elenco chiuso (`NAMES`) o con un prefisso
     *   noto (`PREFIXES`), mai l'intero ambiente: `$_SERVER` contiene anche i
     *   dati della richiesta (`HTTP_*`, `PHP_AUTH_*`) e l'host può avere
     *   variabili con nomi generici che non c'entrano con il sito;
     * - un valore vuoto vale «non impostato» (come in `Credentials::envOrRow`):
     *   non si copia e non impedisce al `.env` di valorizzare la chiave;
     * - mai sovrascrivere un valore già presente in `$_ENV`;
     * - `FTP_*`, `BWS_*`, `CLOUDFLARE_*` e `NPM_GITHUB_TOKEN` non sono nell'elenco:
     *   sono credenziali di deploy, non servono al sito a runtime.
     *
     * Idempotente e senza effetti collaterali oltre a `$_ENV`.
     */
    final class EnvProcess
    {
        /** Nomi esatti ammessi (oltre ai prefissi). */
        public const NAMES = [
            'ASSETS_VERSION',
            'API_KEY',
            'GITHUB_API_TOKEN',
            'IMAGE_MISSING_LABEL',
            'QUERY_COUNTER_TOP',
            'SYNC_AUTO_EXPORT',
            'SYNC_TABLES',
            'USER_USERNAME',
            'USER_NAME',
            'USER_SURNAME',
            'USER_EMAIL',
            'USER_PASSWORD',
        ];

        /** Prefissi ammessi. */
        public const PREFIXES = [
            'APP_',
            'DB_',
            'MAIL_',
            'BREVO_',
            'GOOGLE_',
            'APPLE_',
            'G_RECAPTCHA_',
            'G_MAPS_',
            'G_TAG_MANAGER_',
            'GCP_',
            'KLAVIYO_',
            'IPINFO_',
            'STRIPE_',
            'PAYPAL_',
            'NEXI_',
            'FATTURE_IN_CLOUD_',
            'OAUTH_',
            'MEMORY_PROFILE_',
        ];

        /** True se il nome può essere portato in `$_ENV`. */
        public static function isAllowed(string $name): bool
        {
            if ($name === '') {
                return false;
            }

            if (in_array($name, self::NAMES, true)) {
                return true;
            }

            foreach (self::PREFIXES as $prefix) {
                if (strncmp($name, $prefix, strlen($prefix)) === 0) {
                    return true;
                }
            }

            return false;
        }

        /**
         * Copia in `$_ENV` le variabili ammesse del processo che non ci sono già.
         * Fonti, in ordine: `$_SERVER` (fastcgi_param di nginx/Apache), poi `getenv()`.
         */
        public static function hydrate(): void
        {
            $candidates = [];

            foreach (getenv() as $name => $value) {
                $candidates[(string) $name] = $value;
            }

            // `$_SERVER` vince su getenv() quando i due divergono (fastcgi_param).
            foreach ($_SERVER as $name => $value) {
                if (is_string($name) && is_string($value)) {
                    $candidates[$name] = $value;
                }
            }

            foreach ($candidates as $name => $value) {
                if (!is_string($value) || trim($value) === '' || !self::isAllowed($name)) {
                    continue;
                }

                if (array_key_exists($name, $_ENV) && trim((string) $_ENV[$name]) !== '') {
                    continue;
                }

                $_ENV[$name] = $value;
            }
        }
    }
