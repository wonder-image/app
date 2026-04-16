<?php

    use Wonder\App\Credentials;
    use Wonder\Sql\ConnectionPool;

    # Database
        $DB->hostname = trim((string) ($_ENV['DB_HOSTNAME'] ?? ''));
        $DB->username = trim((string) ($_ENV['DB_USERNAME'] ?? ''));
        $DB->password = (string) ($_ENV['DB_PASSWORD'] ?? '');
        $DB->charset = trim((string) ($_ENV['DB_CHARSET'] ?? 'latin1'));
        $DB->collation = trim((string) ($_ENV['DB_COLLATION'] ?? 'latin1_swedish_ci'));
        $DB->database = [];

        $databases = trim((string) ($_ENV['DB_DATABASE'] ?? ''));

        if ($databases !== '') {

            foreach (explode(',', $databases) as $database) {
                $database = trim($database);

                if ($database === '') {
                    continue;
                }

                $values = explode(':', str_replace(' ', '', $database), 2);
                $alias = isset($values[1]) ? $values[0] : 'main';
                $name = $values[1] ?? $values[0];

                $DB->database[$alias] = $name;
            }

        }

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
