<?php

    if (is_array($DB->database)) {
        
        if (isset($DB->database['main'])) {

            $main = $DB->database['main'];
            $DB_MAIN = new mysqli($DB->hostname, $DB->username, $DB->password, $main);
            if ($DB_MAIN->connect_errno) { echo "Connessione a MySQL fallita: ($DB_MAIN->connect_errno) $DB_MAIN->connect_error"; }

            $mysqli = $DB_MAIN;
    
        } else {

            echo "<b>Errore</b> database main non creato";

        }

        if (isset($DB->database['stats'])) {
            
            $stats = $DB->database['stats'];
            $DB_STATS = new mysqli($DB->hostname, $DB->username, $DB->password, $stats);
            if ($mysqli->connect_errno) { echo "Connessione a MySQL fallita: ($DB_STATS->connect_errno) $DB_STATS->connect_error"; }

        } else {

            $DB_STATS = false;

        }
        
    } else {

        $DB_MAIN = new mysqli($DB->hostname, $DB->username, $DB->password, $DB->database);
        if ($DB_MAIN->connect_errno) { echo "Connessione a MySQL fallita: ($DB_MAIN->connect_errno) $DB_MAIN->connect_error"; }

        $mysqli = $DB_MAIN;

    }

?>