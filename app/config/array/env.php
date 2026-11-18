<?php

    # L'ambiente reale del processo vince sul .env (Dotenv è immutabile):
    # va portato in $_ENV prima di caricare il file.
    \Wonder\App\EnvProcess::hydrate();

    $ENV_FILE = Dotenv\Dotenv::createImmutable($ROOT);
    $ENV_FILE->safeLoad();
    \Wonder\App\EnvCompat::apply();