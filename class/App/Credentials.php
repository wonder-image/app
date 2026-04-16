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

                self::$ENV = Dotenv::createImmutable(__DIR__);
                self::$ENV->safeLoad();

            }

        }

        public static function database(): object
        {
            
            self::loadEnv();
            
            if (empty(self::$DB)) {
                
                self::$ENV->required([ 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE' ]);

                self::$DB = (object) [];
                self::$DB->hostname = $_ENV['DB_HOSTNAME'];
                self::$DB->username = $_ENV['DB_USERNAME'];
                self::$DB->password = $_ENV['DB_PASSWORD'];
                self::$DB->database = explode(',', $_ENV['DB_DATABASE']);
                self::$DB->charset = trim((string) ($_ENV['DB_CHARSET'] ?? 'latin1'));
                self::$DB->collation = trim((string) ($_ENV['DB_COLLATION'] ?? 'latin1_swedish_ci'));

                if (self::$DB->charset === '') {
                    self::$DB->charset = 'latin1';
                }

                if (self::$DB->collation === '') {
                    self::$DB->collation = 'latin1_swedish_ci';
                }

                # Trasformo in un array leggibile i dettagli passati dal file .env 
                    $DATABASE_ARRAY = [];
                        
                    if (count(self::$DB->database) > 1) {

                        foreach (self::$DB->database as $k => $v) {
                            
                            $A_VALUES = explode(':', str_replace(' ', '', $v));
                            $DATABASE_ARRAY[$A_VALUES[0]] = $A_VALUES[1];

                        }

                        self::$DB->database = $DATABASE_ARRAY;

                    } else {

                        $DATABASE = explode(':', str_replace(' ', '', self::$DB->database[0]));
                        $DATABASE_ARRAY['main'] = isset($DATABASE[1]) ? $DATABASE[1] : self::$DB->database[0];

                        self::$DB->database = $DATABASE_ARRAY;

                    }

                #

                self::$DB->database['information_schema'] = "INFORMATION_SCHEMA";

            }
            
            return self::$DB;
            
        }

        public static function mail() {

            if (empty(self::$MAIL)) {

                $row = self::safeSecurityRow();
                self::$MAIL = self::mailDefaults();
                self::$MAIL->host = $row['mail_host'] ?? self::$MAIL->host;
                self::$MAIL->username = $row['mail_username'] ?? self::$MAIL->username;
                self::$MAIL->password = $row['mail_password'] ?? self::$MAIL->password;
                self::$MAIL->port = $row['mail_port'] ?? self::$MAIL->port;
                self::$MAIL->service = $row['mail_service'] ?? self::$MAIL->service;
                self::$MAIL->brevo_api_key = $row['brevo_api_key'] ?? self::$MAIL->brevo_api_key;

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

                $row = self::safeSecurityRow();
                self::$API = self::apiDefaults();
                self::$API->key = $row['api_key'] ?? self::$API->key;
                self::$API->gcp_project_id = $row['gcp_project_id'] ?? self::$API->gcp_project_id;
                self::$API->gcp_api_key = $row['gcp_api_key'] ?? self::$API->gcp_api_key;
                self::$API->gcp_client_api_key = $row['gcp_client_api_key'] ?? self::$API->gcp_api_key;
                self::$API->google_oauth_client_id = $row['google_oauth_client_id'] ?? self::$API->google_oauth_client_id;
                self::$API->google_oauth_client_secret = $row['google_oauth_client_secret'] ?? self::$API->google_oauth_client_secret;
                self::$API->google_oauth_redirect_uri = $row['google_oauth_redirect_uri'] ?? self::$API->google_oauth_redirect_uri;
                self::$API->apple_oauth_client_id = $row['apple_oauth_client_id'] ?? self::$API->apple_oauth_client_id;
                self::$API->apple_oauth_team_id = $row['apple_oauth_team_id'] ?? self::$API->apple_oauth_team_id;
                self::$API->apple_oauth_key_id = $row['apple_oauth_key_id'] ?? self::$API->apple_oauth_key_id;
                self::$API->apple_oauth_private_key = $row['apple_oauth_private_key'] ?? self::$API->apple_oauth_private_key;
                self::$API->apple_oauth_redirect_uri = $row['apple_oauth_redirect_uri'] ?? self::$API->apple_oauth_redirect_uri;
                self::$API->g_recaptcha_site_key = $row['g_recaptcha_site_key'] ?? self::$API->g_recaptcha_site_key;
                self::$API->g_maps_place_id = $row['g_maps_place_id'] ?? self::$API->g_maps_place_id;
                self::$API->klaviyo_api_key = $row['klaviyo_api_key'] ?? self::$API->klaviyo_api_key;
                self::$API->ipinfo_api_key = $row['ipinfo_api_key'] ?? self::$API->ipinfo_api_key;

                self::$API->stripe_test = isset($row['stripe_test'])
                    ? filter_var($row['stripe_test'], FILTER_VALIDATE_BOOLEAN)
                    : self::$API->stripe_test;
                self::$API->stripe_test_key = $row['stripe_test_key'] ?? self::$API->stripe_test_key;
                self::$API->stripe_private_key = $row['stripe_private_key'] ?? self::$API->stripe_private_key;
                self::$API->stripe_account_id = $row['stripe_account_id'] ?? self::$API->stripe_account_id;
                self::$API->stripe_test_account_id = $row['stripe_test_account_id'] ?? self::$API->stripe_test_account_id;
                self::$API->stripe_id = self::$API->stripe_test ? self::$API->stripe_test_account_id : self::$API->stripe_account_id;
                self::$API->stripe_api_key = self::$API->stripe_test ? self::$API->stripe_test_key : self::$API->stripe_private_key;

                self::$API->fatture_in_cloud_app_id = $row['fatture_in_cloud_app_id'] ?? self::$API->fatture_in_cloud_app_id;
                self::$API->fatture_in_cloud_client_id = $row['fatture_in_cloud_client_id'] ?? self::$API->fatture_in_cloud_client_id;
                self::$API->fatture_in_cloud_client_secret = $row['fatture_in_cloud_client_secret'] ?? self::$API->fatture_in_cloud_client_secret;
                self::$API->fatture_in_cloud_company_id = $row['fatture_in_cloud_company_id'] ?? self::$API->fatture_in_cloud_company_id;
                self::$API->fatture_in_cloud_token = $row['fatture_in_cloud_token'] ?? self::$API->fatture_in_cloud_token;

            }

            return self::$API;

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
