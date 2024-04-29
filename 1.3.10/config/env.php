<?php

    $ENV_FILE = Dotenv\Dotenv::createImmutable($ROOT);
    // $ENV_FILE->required('APP_DEBUG')->isBoolean();
    // $ENV_FILE->required(['APP_URL', 'USER_NAME', 'USER_SURNAME', 'USER_EMAIL', 'USER_USERNAME', 'USER_PASSWORD', 'DB_HOSTNAME', 'DB_USERNAME', 'DB_PASSWORD', 'DB_DATABASE']);
    $ENV_FILE->safeLoad();