<?php

    require_once __DIR__."/array/array.php";
    require_once __DIR__."/style/style.php";
    require_once __DIR__."/connection/connection.php";

    \Wonder\App\TranslationBootstrap::preload($ROOT_APP, $ROOT);

    # `wonder-image.php` viene incluso dentro un metodo del dispatcher: finché
    # non pubblichiamo lo scope, `$DEFAULT`, `$PATH` & co. restano variabili
    # locali e `$GLOBALS['DEFAULT']` non esiste. `app/app.php` costruisce le
    # prepare schema dei Model, e quelle schema leggono i globals (le misure
    # dell'icona app, per dirne una): senza questa riga leggerebbero sempre il
    # fallback vuoto e il campo finirebbe con le misure responsive del sito.
    \Wonder\App\LegacyGlobals::capture(get_defined_vars());

    require_once __DIR__."/app/app.php";

    $MODULES = \Wonder\App\Module\ConfigRepository::all();

    foreach (\Wonder\App\Module\Registry::bootFiles() as $MODULE_BOOT_FILE) {
        require_once $MODULE_BOOT_FILE;
    }

    $customConfigFile = $ROOT."/custom/config/config.php";
    if (file_exists($customConfigFile)) {
        require_once $customConfigFile; # Configurazioni CUSTOM
    }
