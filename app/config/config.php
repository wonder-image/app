<?php

    require_once __DIR__."/array/array.php";
    require_once __DIR__."/style/style.php";
    require_once __DIR__."/connection/connection.php";

    \Wonder\App\TranslationBootstrap::preload($ROOT_APP, $ROOT);

    require_once __DIR__."/app/app.php";

    $MODULES = \Wonder\App\Module\ConfigRepository::all();

    foreach (\Wonder\App\Module\Registry::bootFiles() as $MODULE_BOOT_FILE) {
        require_once $MODULE_BOOT_FILE;
    }

    $customConfigFile = $ROOT."/custom/config/config.php";
    if (file_exists($customConfigFile)) {
        require_once $customConfigFile; # Configurazioni CUSTOM
    }
