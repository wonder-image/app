<?php

    namespace Wonder\Sql;

    use Wonder\App\Credentials;
    
    use mysqli;
    use Exception;

    class Connection {

        private static $hostname, $username, $password, $database;

        private static $connection = [];

        
        public function __construct( $hostname = null, $username = null, $password = null, $database = null ) 
        {

            self::$hostname = ($hostname === null) ? Credentials::database()->hostname : $hostname;
            self::$username = ($username === null) ? Credentials::database()->username : $username;
            self::$password = ($password === null) ? Credentials::database()->password : $password;
            self::$database = ($database === null) ? Credentials::database()->database['main'] : $database;

        }

        public static function Connect( $database = null): mysqli 
        { 

            $database = ($database === null) ? self::$database : $database;

            $connectionCode = base64_encode(self::$hostname.' | '.self::$username.' | '.self::$password.' | '.$database);

            if (isset(self::$connection[$connectionCode])) {

                return self::$connection[$connectionCode];

            } else {

                $mysqli = new mysqli( self::$hostname, self::$username, self::$password, $database );
                        
                if ($mysqli->connect_error) {
                    
                    $message = "\r\n";
                    $message .= "Connessione al database ".self::$database." fallita \r\n";
                    $message .= "\r\n";
                    $message .= "Error N°$mysqli->connect_errno\r\n";
                    $message .= "$mysqli->connect_error\r\n";
        
                    throw new Exception($message);

                } else {

                    $mysqli->set_charset("latin1");

                }

                self::$connection[$connectionCode] = $mysqli;

                return $mysqli;

            }

        }


    }