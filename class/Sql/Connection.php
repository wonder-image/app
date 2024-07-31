<?php

    namespace Wonder\Sql;

    use mysqli;
    use Exception;

    class Connection {

        private $hostname, $username, $password, $database;
        
        public function __construct( $hostname, $username, $password, $database ) {

            $this->hostname = $hostname;
            $this->username = $username;
            $this->password = $password;
            $this->database = $database;

        }

        public function Connect() : mysqli 
        { 

            $mysqli = new mysqli( $this->hostname, $this->username, $this->password, $this->database );
                    
            if ($mysqli->connect_error) {
                
                $message = "\r\n";
                $message .= "Connessione al datatbase $this->database fallita \r\n";
                $message .= "\r\n";
                $message .= "Error N°$mysqli->connect_errno\r\n";
                $message .= "$mysqli->connect_error\r\n";
    
                throw new Exception($message);

            } else {

                $mysqli->set_charset("latin1");

            }

            return $mysqli;

        }


    }