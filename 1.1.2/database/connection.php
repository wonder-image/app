<?php

    $mysqli = new mysqli($DB->hostname, $DB->username, $DB->password, $DB->database);
    if ($mysqli->connect_errno) {
        echo "Connessione a MySQL fallita: ($mysqli->connect_errno) $mysqli->connect_error";
    }

?>