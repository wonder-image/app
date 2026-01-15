<?php

    use Wonder\App\Credentials;
    use Wonder\Sql\ConnectionPool;

    # Database
        $DB->hostname = Credentials::database()->hostname;
        $DB->username = Credentials::database()->username;
        $DB->password = Credentials::database()->password;
        $DB->database = Credentials::database()->database;

    # Connessione ai database (lazy: apri solo quando serve)
        $MYSQLI_CONNECTION = new ConnectionPool();
        $mysqli = $MYSQLI_CONNECTION['main'];

    # Mail
        $MAIL = Credentials::mail();

    # Api
        $API = Credentials::api();
        $API->DataTables = $PATH->apiDT;
