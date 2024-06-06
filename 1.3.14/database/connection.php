<?php

    use Wonder\Sql\Connection;

    $MYSQLI_CONNECTION = [];

    foreach ($DB->database as $key => $database) {
        
        $connection = new Connection( $DB->hostname, $DB->username, $DB->password, $database );
        $MYSQLI_CONNECTION[$key] = $connection->Connect();

    }

    $mysqli = $MYSQLI_CONNECTION['main'];