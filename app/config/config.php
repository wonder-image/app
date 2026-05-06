<?php

    require_once __DIR__."/array/array.php";
    require_once __DIR__."/style/style.php";
    require_once __DIR__."/connection/connection.php";
    require_once __DIR__."/app/app.php";

    $MODULES = \Wonder\App\Module\ConfigRepository::all();

    $customConfigFile = $ROOT."/custom/config/config.php";
    if (file_exists($customConfigFile)) {
        require_once $customConfigFile; # Configurazioni CUSTOM
    }
