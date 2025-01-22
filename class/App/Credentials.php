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


        public static function api(): object
        {

            self::database();

            if (empty(self::$API)) {
                
                self::$API = (object) [];
                self::$API->endpoint = "https://api.wonderimage.it/v1.0";

                $connection = new Connection( 
                    self::database()->hostname, 
                    self::database()->username, 
                    self::database()->password, 
                    self::database()->database['main']
                );

                $query = new Query($connection->Connect());

                $exists = ($query->TableExists('security') && $query->Select('security', [ 'id' => 1 ], 1)->exists) ? true : false;

                if ($exists) {

                    $row = $query->Select('security', [ 'id' => 1 ], 1)->row;

                    self::$API->key = $row['api_key'];
                    self::$API->g_recaptcha_site_key = $row['g_recaptcha_site_key'];
                    self::$API->g_maps_place_id = $row['g_maps_place_id'];

                    if (empty($row['gcp_project_id']) || empty($row['gcp_api_key'])) {
                        
                        $api = wiApi('/service/google/credentials/');
                        $wi = json_decode( $api, true);
                        
                        self::$API->gcp_project_id = $wi['success'] == 'true' ? $wi['response']['project_id'] : '';
                        self::$API->gcp_api_key = $wi['success'] == 'true' ? $wi['response']['api_key'] : '';

                    } else {

                        self::$API->gcp_project_id = $row['gcp_project_id'];
                        self::$API->gcp_api_key = $row['gcp_api_key'];

                    }
                    
                } else {

                    $wi = json_decode(wiApi('/service/google/credentials/'), true);

                    self::$API->key = strtolower(Rand::generate(5).'-'.Rand::generate(5).'-'.Rand::generate(5).'-'.Rand::generate(5));
                    self::$API->gcp_project_id = $wi['success'] ? $wi['response']['project_id'] : '';
                    self::$API->gcp_api_key = $wi['success'] ? $wi['response']['project_id'] : '';
                    self::$API->g_recaptcha_site_key = '';
                    self::$API->g_maps_place_id = '';

                }

            }

            return self::$API;

        }

    }