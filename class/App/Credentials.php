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

                self::$API->key = ($query->TableExists('security') && $query->Select('security', [ 'id' => 1 ], 1)->exists) ? $query->Select('security', [ 'id' => 1 ], 1)->row['api_key'] : strtolower(Rand::generate(5).'-'.Rand::generate(5).'-'.Rand::generate(5).'-'.Rand::generate(5));

            }

            return self::$API;

        }

    }