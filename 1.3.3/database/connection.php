<?php

    $MYSQLI_CONNECTION = [];

    foreach ($DB->database as $key => $database) {

        $MYSQLI_CONNECTION[$key] = new mysqli($DB->hostname, $DB->username, $DB->password, $database);

        if ($MYSQLI_CONNECTION[$key]->connect_errno) { 
            echo "Connessione a MySQL fallita: ({$MYSQLI_CONNECTION[$key]->connect_errno}) {$MYSQLI_CONNECTION[$key]->connect_error}"; 
        }

    }

    $mysqli = $MYSQLI_CONNECTION['main'];

?>