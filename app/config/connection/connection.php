<?php

    use Wonder\App\Credentials;
    use Wonder\Sql\ConnectionPool;

    $databaseCredentials = Credentials::database();

    # Database
        $DB->hostname = trim((string) ($databaseCredentials->hostname ?? ''));
        $DB->username = trim((string) ($databaseCredentials->username ?? ''));
        $DB->password = (string) ($databaseCredentials->password ?? '');
        $DB->charset = trim((string) ($databaseCredentials->charset ?? 'latin1'));
        $DB->collation = trim((string) ($databaseCredentials->collation ?? 'latin1_swedish_ci'));
        $DB->database = is_array($databaseCredentials->database ?? null)
            ? $databaseCredentials->database
            : [];

        if (!isset($DB->database['information_schema'])) {
            $DB->database['information_schema'] = 'INFORMATION_SCHEMA';
        }

    # Connessione ai database (lazy: apri solo quando serve)
        $MYSQLI_CONNECTION = new ConnectionPool(
            $DB->hostname !== '' ? $DB->hostname : null,
            $DB->username !== '' ? $DB->username : null,
            $DB->password !== '' ? $DB->password : null,
            !empty($DB->database) ? $DB->database : null
        );
        $mysqli = null;

    # Mail
        $MAIL = Credentials::mailDefaults();

    # Api
        $API = Credentials::apiDefaults();
        $API->DataTables = $PATH->apiDT;
