<?php

    namespace Wonder\App;

    use Dotenv\Dotenv;

    use Throwable;
    use Wonder\Sql\Connection;
    use Wonder\Sql\Query;
    use Wonder\Support\Text\Random;

    class Credentials {

        protected static $ENV;
        protected static $DB;
        protected static $API;
        protected static $MAIL;
        protected static $appKey; # Chiave per la codifica
        protected static $appToken; # Utilizzato per le chiamate API interne

        public static function loadEnv()
        {

            if (empty(self::$ENV)) {

                self::$ENV = Dotenv::createImmutable(self::envRoot());
                self::$ENV->safeLoad();
                EnvCompat::apply();

            }

        }

        private static function envRoot(): string
        {

            $root = LegacyGlobals::get('ROOT', defined('ROOT') ? ROOT : '');

            if (is_string($root) && trim($root) !== '' && is_file(rtrim($root, '/').'/.env')) {
                return rtrim($root, '/');
            }

            $cwd = getcwd();

            if (is_string($cwd) && trim($cwd) !== '' && is_file(rtrim($cwd, '/').'/.env')) {
                return rtrim($cwd, '/');
            }

            return dirname(__DIR__, 3);

        }

        public static function database(): object
        {

            self::loadEnv();

            if (empty(self::$DB)) {

                // Le credenziali DB possono mancare durante il setup iniziale
                // di un nuovo progetto (composer create-project → forge config
                // → npm install eccetera, ESEGUITI prima di `forge db:init` o
                // della popolazione manuale del `.env`). Storicamente qui
                // c'era un `Dotenv::required(['DB_HOSTNAME', ...])` che
                // throwava `ValidationException` con messaggio criptico
                // "DB_HOSTNAME is missing", bloccando il setup.
                //
                // Ora restituiamo un oggetto con valori (eventualmente vuoti)
                // letti via `$_ENV[…] ?? ''`. Se davvero serve la DB, sarà
                // `Connection::Connect()` a fallire con un errore
                // localizzato e chiaro del driver MySQL ("Empty host",
                // "Access denied", ecc.). I metodi `api()` e `mail()`
                // continuano a funzionare grazie al try/catch di
                // `safeRow()` che cattura l'errore di connessione e
                // ricade sui defaults.
                //
                // Per verificare upfront se le credenziali ci sono:
                // `Credentials::hasDatabaseCredentials()`.

                $hostname = self::firstEnvValue(['DB_HOSTNAME', 'DB_HOST']);
                $username = self::firstEnvValue(['DB_USERNAME', 'DB_USER']);
                $password = self::firstEnvValue(['DB_PASSWORD'], false); // intenzionalmente NON trim: una password può iniziare/finire con spazi
                $database = self::firstEnvValue(['DB_DATABASE', 'DB_NAME']);

                self::$DB = (object) [];
                self::$DB->hostname = $hostname;
                self::$DB->username = $username;
                self::$DB->password = $password;
                self::$DB->charset = trim((string) ($_ENV['DB_CHARSET'] ?? 'latin1'));
                self::$DB->collation = trim((string) ($_ENV['DB_COLLATION'] ?? 'latin1_swedish_ci'));

                if (self::$DB->charset === '') {
                    self::$DB->charset = 'latin1';
                }

                if (self::$DB->collation === '') {
                    self::$DB->collation = 'latin1_swedish_ci';
                }

                # Trasformo in un array leggibile i dettagli passati dal file .env
                # Formato supportato:
                #   - singolo:  "main_db"            → ['main' => 'main_db']
                #   - alias:    "alias:db_name"      → ['alias' => 'db_name']
                #   - multipli: "main:a, stats:b"    → ['main' => 'a', 'stats' => 'b']
                #
                # Se `DB_DATABASE` è vuoto (setup iniziale), restituisco
                # un array con 'main' vuoto: i consumer che accedono a
                # `database['main']` ricevono `''` invece di errore di
                # "undefined index", e il fail vero arriva al
                # `mysqli_connect` di `Connection::Connect()`.

                    $DATABASE_ARRAY = [];

                    if ($database === '') {

                        $DATABASE_ARRAY['main'] = '';

                    } else {

                        $databases = explode(',', $database);

                        if (count($databases) > 1) {

                            foreach ($databases as $v) {

                                $A_VALUES = explode(':', str_replace(' ', '', $v));
                                if (isset($A_VALUES[0], $A_VALUES[1]) && $A_VALUES[0] !== '') {
                                    $DATABASE_ARRAY[$A_VALUES[0]] = $A_VALUES[1];
                                }

                            }

                        } else {

                            $DATABASE = explode(':', str_replace(' ', '', $databases[0]));
                            $DATABASE_ARRAY['main'] = isset($DATABASE[1]) ? $DATABASE[1] : $databases[0];

                        }

                    }

                #

                self::$DB->database = $DATABASE_ARRAY;
                self::$DB->database['information_schema'] = "INFORMATION_SCHEMA";

            }

            return self::$DB;

        }

        /**
         * Verifica se nel `.env` ci sono credenziali DB plausibilmente complete.
         *
         * Utile come early-check nei comandi di setup (es. `forge update --local`)
         * per dare un messaggio chiaro tipo "esegui `forge db:init` prima"
         * invece di lasciar fallire `Credentials::database()` o `mysqli_connect`
         * con errori da bootstrap.
         *
         * La password può legittimamente essere vuota (es. utente root su Mac
         * locale), quindi non rientra nei requisiti minimi.
         */
        public static function hasDatabaseCredentials(): bool
        {

            self::loadEnv();

            $hostname = self::firstEnvValue(['DB_HOSTNAME', 'DB_HOST']);
            $username = self::firstEnvValue(['DB_USERNAME', 'DB_USER']);
            $database = self::firstEnvValue(['DB_DATABASE', 'DB_NAME']);

            return $hostname !== '' && $username !== '' && $database !== '';

        }

        private static function firstEnvValue(array $keys, bool $trim = true): string
        {

            foreach ($keys as $key) {

                if (!is_string($key) || $key === '') {
                    continue;
                }

                $value = self::readEnvValue($key, $trim);

                if ($value !== '') {
                    return $value;
                }

            }

            return '';

        }

        private static function readEnvValue(string $key, bool $trim = true): string
        {

            if (array_key_exists($key, $_ENV)) {
                $value = (string) $_ENV[$key];
                return $trim ? trim($value) : $value;
            }

            if (array_key_exists($key, $_SERVER)) {
                $value = (string) $_SERVER[$key];
                return $trim ? trim($value) : $value;
            }

            $value = getenv($key);

            if ($value === false) {
                return '';
            }

            $value = (string) $value;

            return $trim ? trim($value) : $value;

        }

        public static function mail() {

            if (empty(self::$MAIL)) {

                self::loadEnv();

                $row = self::safeSecurityRow();
                self::$MAIL = self::mailDefaults();
                self::$MAIL->host          = self::envOrRow('MAIL_HOST',          $row, 'mail_host',          self::$MAIL->host);
                self::$MAIL->username      = self::envOrRow('MAIL_USERNAME',      $row, 'mail_username',      self::$MAIL->username);
                self::$MAIL->password      = self::envOrRow('MAIL_PASSWORD',      $row, 'mail_password',      self::$MAIL->password);
                self::$MAIL->port          = self::envOrRow('MAIL_PORT',          $row, 'mail_port',          self::$MAIL->port);
                self::$MAIL->service       = self::envOrRow('MAIL_SERVICE',       $row, 'mail_service',       self::$MAIL->service);
                self::$MAIL->brevo_api_key = self::envOrRow('BREVO_API_KEY',      $row, 'brevo_api_key',      self::$MAIL->brevo_api_key);

            }

            return self::$MAIL;

        }

        public static function appKey(): string
        {

            if (empty(self::$appKey)) {

                self::loadEnv();

                self::$ENV->required([ 'APP_KEY' ]);

                self::$appKey = $_ENV['APP_KEY'];

            }

            return self::$appKey;

        }

        public static function appToken(): string
        {

            if (empty(self::$appToken)) {
                $row = self::safeRow('api_users', [ 'id' => 1 ]);
                self::$appToken = $row['token'] ?? '';

            }


            return self::$appToken;

        }

        private static function query( string $database = 'main' ): Query 
        {

            self::database();

            $connection = new Connection( 
                self::database()->hostname, 
                self::database()->username, 
                self::database()->password, 
                self::database()->database[$database]
            );

            return new Query($connection->Connect());

        }

        public static function api(): object
        {

            if (empty(self::$API)) {

                self::loadEnv();

                $row = self::safeSecurityRow();
                self::$API = self::apiDefaults();

                // Cascade per ogni chiave: $_ENV → DB security row → default
                // di apiDefaults(). `.env` vince per consentire override locali
                // (dev-shared Bitwarden riempie il .env con le chiavi dev senza
                // toccare il DB), restando trasparente in produzione dove il
                // .env di prod NON le include — vince il DB popolato via
                // backend admin.
                self::$API->key                          = self::envOrRow('API_KEY',                          $row, 'api_key',                          self::$API->key);
                self::$API->gcp_project_id               = self::envOrRow('GCP_PROJECT_ID',                   $row, 'gcp_project_id',                   self::$API->gcp_project_id);
                self::$API->gcp_api_key                  = self::envOrRow('GCP_API_KEY',                      $row, 'gcp_api_key',                      self::$API->gcp_api_key);
                self::$API->gcp_client_api_key           = self::envOrRow('GCP_CLIENT_API_KEY',               $row, 'gcp_client_api_key',               self::$API->gcp_api_key);
                self::$API->google_oauth_client_id       = self::envOrRow('GOOGLE_OAUTH_CLIENT_ID',           $row, 'google_oauth_client_id',           self::$API->google_oauth_client_id);
                self::$API->google_oauth_client_secret   = self::envOrRow('GOOGLE_OAUTH_CLIENT_SECRET',       $row, 'google_oauth_client_secret',       self::$API->google_oauth_client_secret);
                self::$API->google_oauth_redirect_uri    = self::envOrRow('GOOGLE_OAUTH_REDIRECT_URI',        $row, 'google_oauth_redirect_uri',        self::$API->google_oauth_redirect_uri);
                self::$API->apple_oauth_client_id        = self::envOrRow('APPLE_OAUTH_CLIENT_ID',            $row, 'apple_oauth_client_id',            self::$API->apple_oauth_client_id);
                self::$API->apple_oauth_team_id          = self::envOrRow('APPLE_OAUTH_TEAM_ID',              $row, 'apple_oauth_team_id',              self::$API->apple_oauth_team_id);
                self::$API->apple_oauth_key_id           = self::envOrRow('APPLE_OAUTH_KEY_ID',               $row, 'apple_oauth_key_id',               self::$API->apple_oauth_key_id);
                self::$API->apple_oauth_private_key      = self::envOrRow('APPLE_OAUTH_PRIVATE_KEY',          $row, 'apple_oauth_private_key',          self::$API->apple_oauth_private_key);
                self::$API->apple_oauth_redirect_uri     = self::envOrRow('APPLE_OAUTH_REDIRECT_URI',         $row, 'apple_oauth_redirect_uri',         self::$API->apple_oauth_redirect_uri);
                self::$API->g_recaptcha_site_key         = self::envOrRow('G_RECAPTCHA_SITE_KEY',             $row, 'g_recaptcha_site_key',             self::$API->g_recaptcha_site_key);
                self::$API->g_recaptcha_secret_key       = self::envOrRow('G_RECAPTCHA_SECRET_KEY',           $row, 'g_recaptcha_secret_key',           self::$API->g_recaptcha_secret_key);
                self::$API->g_maps_place_id              = self::envOrRow('G_MAPS_PLACE_ID',                  $row, 'g_maps_place_id',                  self::$API->g_maps_place_id);
                self::$API->klaviyo_api_key              = self::envOrRow('KLAVIYO_API_KEY',                  $row, 'klaviyo_api_key',                  self::$API->klaviyo_api_key);
                self::$API->ipinfo_api_key               = self::envOrRow('IPINFO_API_KEY',                   $row, 'ipinfo_api_key',                   self::$API->ipinfo_api_key);

                // stripe_test è un booleano: env "true"/"1"/"on" → true.
                $stripeTestEnv = trim((string) ($_ENV['STRIPE_TEST'] ?? ''));
                if ($stripeTestEnv !== '') {
                    self::$API->stripe_test = filter_var($stripeTestEnv, FILTER_VALIDATE_BOOLEAN);
                } elseif (isset($row['stripe_test'])) {
                    self::$API->stripe_test = filter_var($row['stripe_test'], FILTER_VALIDATE_BOOLEAN);
                }

                self::$API->stripe_test_key              = self::envOrRow('STRIPE_TEST_KEY',                  $row, 'stripe_test_key',                  self::$API->stripe_test_key);
                self::$API->stripe_private_key           = self::envOrRow('STRIPE_PRIVATE_KEY',               $row, 'stripe_private_key',               self::$API->stripe_private_key);
                self::$API->stripe_account_id            = self::envOrRow('STRIPE_ACCOUNT_ID',                $row, 'stripe_account_id',                self::$API->stripe_account_id);
                self::$API->stripe_test_account_id       = self::envOrRow('STRIPE_TEST_ACCOUNT_ID',           $row, 'stripe_test_account_id',           self::$API->stripe_test_account_id);
                self::$API->stripe_id                    = self::$API->stripe_test ? self::$API->stripe_test_account_id : self::$API->stripe_account_id;
                self::$API->stripe_api_key               = self::$API->stripe_test ? self::$API->stripe_test_key : self::$API->stripe_private_key;

                self::$API->fatture_in_cloud_app_id        = self::envOrRow('FATTURE_IN_CLOUD_APP_ID',         $row, 'fatture_in_cloud_app_id',         self::$API->fatture_in_cloud_app_id);
                self::$API->fatture_in_cloud_client_id     = self::envOrRow('FATTURE_IN_CLOUD_CLIENT_ID',      $row, 'fatture_in_cloud_client_id',      self::$API->fatture_in_cloud_client_id);
                self::$API->fatture_in_cloud_client_secret = self::envOrRow('FATTURE_IN_CLOUD_CLIENT_SECRET',  $row, 'fatture_in_cloud_client_secret',  self::$API->fatture_in_cloud_client_secret);
                self::$API->fatture_in_cloud_company_id    = self::envOrRow('FATTURE_IN_CLOUD_COMPANY_ID',     $row, 'fatture_in_cloud_company_id',     self::$API->fatture_in_cloud_company_id);
                self::$API->fatture_in_cloud_token         = self::envOrRow('FATTURE_IN_CLOUD_TOKEN',          $row, 'fatture_in_cloud_token',          self::$API->fatture_in_cloud_token);

            }

            return self::$API;

        }

        /**
         * Cascade canonica per le credenziali applicative:
         *
         *   1. `$_ENV[$envKey]`           ← override locale (popolato da
         *                                   dev-shared Bitwarden per le chiavi
         *                                   di sviluppo, oppure ad-hoc dal
         *                                   developer per testing).
         *   2. `$row[$rowKey]`            ← valore di runtime salvato nella
         *                                   tabella `security` via backend
         *                                   admin (single source of truth in
         *                                   produzione).
         *   3. `$default`                 ← hardcoded da `apiDefaults()` /
         *                                   `mailDefaults()`.
         *
         * In produzione il `.env` NON contiene queste chiavi (sono gestite
         * dall'admin nel DB), quindi il comportamento di prod resta invariato:
         * vince sempre il DB. In locale, dev-shared scrive i valori nel `.env`
         * e il sito li usa senza necessità di popolare il DB di sviluppo.
         *
         * Una stringa vuota nel `.env` viene considerata "non impostato"
         * (cosi `KEY=` non shadow-a un valore di DB valido).
         */
        protected static function envOrRow(string $envKey, array $row, string $rowKey, $default)
        {
            $envValue = trim((string) ($_ENV[$envKey] ?? ''));
            if ($envValue !== '') {
                return $envValue;
            }

            $rowValue = $row[$rowKey] ?? null;
            if ($rowValue !== null && $rowValue !== '') {
                return $rowValue;
            }

            return $default;
        }

        public static function mailDefaults(): object
        {

            return (object) [
                'host' => '',
                'username' => '',
                'password' => '',
                'port' => '',
                'service' => 'phpmailer',
                'brevo_api_key' => '',
            ];

        }

        public static function apiDefaults(): object
        {

            $key = strtolower(Random::generate(5).'-'.Random::generate(5).'-'.Random::generate(5).'-'.Random::generate(5));

            return (object) [
                'endpoint' => 'https://api.wonderimage.it/v1.0',
                'key' => $key,
                'gcp_project_id' => '',
                'gcp_api_key' => '',
                'gcp_client_api_key' => '',
                'google_oauth_client_id' => '',
                'google_oauth_client_secret' => '',
                'google_oauth_redirect_uri' => '',
                'apple_oauth_client_id' => '',
                'apple_oauth_team_id' => '',
                'apple_oauth_key_id' => '',
                'apple_oauth_private_key' => '',
                'apple_oauth_redirect_uri' => '',
                'g_recaptcha_site_key' => '',
                'g_recaptcha_secret_key' => '',
                'g_maps_place_id' => '',
                'klaviyo_api_key' => '',
                'ipinfo_api_key' => '',
                'stripe_test' => false,
                'stripe_test_key' => '',
                'stripe_private_key' => '',
                'stripe_account_id' => '',
                'stripe_test_account_id' => '',
                'stripe_id' => '',
                'stripe_api_key' => '',
                'fatture_in_cloud_app_id' => '',
                'fatture_in_cloud_client_id' => '',
                'fatture_in_cloud_client_secret' => '',
                'fatture_in_cloud_company_id' => '',
                'fatture_in_cloud_token' => '',
            ];

        }

        private static function safeSecurityRow(): array
        {

            return self::safeRow('security', [ 'id' => 1 ]);

        }

        private static function safeRow(string $table, array $conditions, string $database = 'main'): array
        {

            try {
                $query = self::query($database);

                if (!$query->TableExists($table)) {
                    return [];
                }

                $result = $query->Select($table, $conditions, 1);

                if (!is_object($result) || empty($result->exists)) {
                    return [];
                }

                return is_array($result->row ?? null) ? $result->row : [];
            } catch (Throwable) {
                return [];
            }

        }

    }
