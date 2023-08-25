<?php

    $MYSQLI_CONNECTION = [];

    if (is_array($DB->database)) {

        foreach ($DB->database as $key => $database) {

            $MYSQLI_CONNECTION[$key] = new mysqli($DB->hostname, $DB->username, $DB->password, $database);

            if ($MYSQLI_CONNECTION[$key]->connect_errno) { 
                echo "Connessione a MySQL fallita: ({$MYSQLI_CONNECTION[$key]->connect_errno}) {$MYSQLI_CONNECTION[$key]->connect_error}"; 
            }

        }

        $mysqli = $MYSQLI_CONNECTION['main'];
        
    } else {

        $MYSQLI_CONNECTION['main'] = new mysqli($DB->hostname, $DB->username, $DB->password, $DB->database);

        if ($MYSQLI_CONNECTION['main']->connect_errno) { 
            echo "Connessione a MySQL fallita: ({$MYSQLI_CONNECTION['main']->connect_errno}) {$MYSQLI_CONNECTION['main']->connect_error}"; 
        }

    }

    $mysqli = $MYSQLI_CONNECTION['main'];

?>