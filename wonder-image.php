<?php

    ini_set ('display_errors', 1);
    ini_set ('display_startup_errors', 1);
    ini_set ('session.autostart', 1);
    error_reporting (E_ALL);

    $VERSION = "0.0.1";

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    $ROOT_APP = __DIR__ . "/$VERSION";

    require_once $ROOT . "/vendor/autoload.php";

    require_once $ROOT_APP . "/config/env.php";
    require_once $ROOT_APP . "/config/array.php";
    require_once $ROOT_APP . "/config/alert.php";
    require_once $ROOT_APP . "/config/permissions.php";
    require_once $ROOT_APP . "/config/table.php";

    // require_once $ROOT_APP . "/custom/config/config.php";

    // require_once $ROOT_APP . "/database/connection.php";
    
    // require_once $ROOT_APP . "/function/function.php";

    // if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP . "/function/backend/function.php"; }
    // if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP . "/function/frontend/function.php"; }
    
    // require_once $ROOT . "/custom/function/function.php";

    // $PAGE = infoPage();
    // $SOCIETY = infoSociety();

    // require_once $ROOT_APP . "/utility/authorize.php";

    // if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP . "/utility/backend/set-up.php"; }
    // if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP . "/utility/frontend/set-up.php"; }

?>