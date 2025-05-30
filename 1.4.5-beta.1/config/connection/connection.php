<?php

    use Wonder\App\Credentials;
    use Wonder\Sql\Connection;

    # Database
        $DB->hostname = Credentials::database()->hostname;
        $DB->username = Credentials::database()->username;
        $DB->password = Credentials::database()->password;
        $DB->database = Credentials::database()->database;

    # Connessione ai database

        $MYSQLI_CONNECTION = [];

        foreach ($DB->database as $key => $database) {
            
            $connection = new Connection( $DB->hostname, $DB->username, $DB->password, $database );

            $MYSQLI_CONNECTION[$key] = $connection->Connect(); # Nome associato al database
            $MYSQLI_CONNECTION[$database] = $connection->Connect(); # Vero nome database

        }

        $mysqli = $MYSQLI_CONNECTION['main'];

    # Mail
        $MAIL = Credentials::mail();

    # Api
        $API = Credentials::api();
        $API->DataTables = $PATH->apiDT;