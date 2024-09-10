<?php

    # Path
        define("APP_URL", $_ENV['APP_URL']);
        define("ROOT", $ROOT);
        define("APP_VERSION", $APP_VERSION);
        define("ASSETS_VERSION", $_ENV['ASSETS_VERSION']);

        $PATH = new Wonder\App\Path;
        