<?php

    namespace Wonder\App;

    use Dotenv\Dotenv;

    use Wonder\Sql\Connection;
    use Wonder\Sql\Query;
    use Wonder\Plugin\Custom\String\Rand;

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
                
                self::$MAIL = (object) [];

                $query = self::query();

                $exists = ($query->TableExists('security') && $query->Select('security', [ 'id' => 1 ], 1)->exists) ? true : false;

                $row = $exists ? $query->Select('security', [ 'id' => 1 ], 1)->row : [];

                self::$MAIL->host = $row['mail_host'] ?? '';
                self::$MAIL->username = $row['mail_username'] ?? '';
                self::$MAIL->password = $row['mail_password'] ?? '';
                self::$MAIL->port = $row['mail_port'] ?? '';

            }
            
            return self::$MAIL;

        }

        public static function appKey(): string
        {

            if (empty(self::$appToken)) {

                self::loadEnv();

                self::$ENV->required([ 'APP_KEY' ]);

                self::$appKey = $_ENV['APP_KEY'];

            }

            return self::$appKey;

        }

        public static function appToken(): string
        {

            if (empty(self::$appToken)) {

                $query = self::query();

                $exists = ($query->TableExists('api_users') && $query->Select('api_users', [ 'id' => 1 ], 1)->exists) ? true : false;

                $row = $exists ? $query->Select('api_users', [ 'id' => 1 ], 1)->row : [];

                self::$appToken = $row['token'];

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
                
                self::$API = (object) [];
                self::$API->endpoint = "https://api.wonderimage.it/v1.0";

                $query = self::query();

                $exists = ($query->TableExists('security') && $query->Select('security', [ 'id' => 1 ], 1)->exists) ? true : false;

                $row = $exists ? $query->Select('security', [ 'id' => 1 ], 1)->row : [];

                self::$API->key = $row['api_key'] ?? strtolower(Rand::generate(5).'-'.Rand::generate(5).'-'.Rand::generate(5).'-'.Rand::generate(5));
                self::$API->gcp_project_id = $row['gcp_project_id'] ?? '';
                self::$API->gcp_api_key = $row['gcp_api_key'] ?? '';
                self::$API->g_recaptcha_site_key = $row['g_recaptcha_site_key'] ?? '';
                self::$API->g_maps_place_id = $row['g_maps_place_id'] ?? '';

                self::$API->stripe_test = isset($row['stripe_test']) ? filter_var($row['stripe_test'], FILTER_VALIDATE_BOOLEAN) : false;
                self::$API->stripe_test_key = $row['stripe_test_key'] ?? '';
                self::$API->stripe_private_key = $row['stripe_private_key'] ?? '';

                self::$API->stripe_account_id = $row['stripe_account_id'] ?? '';
                self::$API->stripe_test_account_id = $row['stripe_test_account_id'] ?? '';

                switch (self::$API->stripe_test) {
                    case true:
                        self::$API->stripe_id = self::$API->stripe_test_account_id;
                        self::$API->stripe_api_key = self::$API->stripe_test_key;
                        break;
                    default:
                        self::$API->stripe_id = self::$API->stripe_account_id;
                        self::$API->stripe_api_key = self::$API->stripe_private_key;
                        break;
                }

            }

            return self::$API;

        }

    }