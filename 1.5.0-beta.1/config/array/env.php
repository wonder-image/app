<?php

    $ENV_FILE = Dotenv\Dotenv::createImmutable($ROOT);
    $ENV_FILE->safeLoad();