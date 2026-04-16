<?php

    # Path
        define("APP_URL", (string) ($_ENV['APP_URL'] ?? ''));
        define("ROOT", $ROOT);
        define("APP_VERSION", $APP_VERSION);
        define("ASSETS_VERSION", (string) ($_ENV['ASSETS_VERSION'] ?? 'dev'));

        $PATH = new Wonder\App\Path;
        
