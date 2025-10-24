<?php

    require_once __DIR__."/array/array.php";
    require_once __DIR__."/style/style.php";
    require_once __DIR__."/connection/connection.php";
    require_once __DIR__."/app/app.php";
    require_once $ROOT."/custom/config/config.php"; # Configurazioni CUSTOM

    if (!defined('RESPONSIVE_IMAGE_SIZES')) {
        define('RESPONSIVE_IMAGE_SIZES', [ 240, 480, 620, 960, 1200, 1440, 1920, 2400 ]);
    }

    if (!defined('RESPONSIVE_IMAGE_WEBP')) {
        define('RESPONSIVE_IMAGE_WEBP', true);
    }