<?php

    namespace Wonder\Sql\Utility;

    use Exception;
    
    class Error {

        public function __construct ( $action, $table, $query, $mysqli ) {

            if ($action == 'Table') { $ALERT = 951; } 
            elseif ($action == 'Insert') { $ALERT = 952; }
            elseif ($action == 'Modify') { $ALERT = 953; }
            elseif ($action == 'Select') { $ALERT = 954; }

            $errorN = $mysqli->errno;
            $error = $mysqli->error;
    
            $message = "\r\n";
            $message .= "Action: $action\r\n";
            $message .= "Table: $table\r\n";
            $message .= "Query: $query\r\n";
            $message .= "\r\n";
            $message .= "Error N°$errorN\r\n";
            $message .= "$error\r\n";
            $message .= "\r\n";

            throw new Exception($message);

        }

    }