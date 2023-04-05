<?php

    ini_set ('display_errors', 1);
    ini_set ('display_startup_errors', 1);
    ini_set ('session.autostart', 1);
    error_reporting (E_ALL);

    session_start();

    $VERSION = "0.0.1";

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    $ROOT_APP = __DIR__ . "/$VERSION";

    require $ROOT_APP."/utility/array.php";
    require $ROOT_APP."/utility/alert.php";

    require $ROOT."/custom/config/config.php";

    require $ROOT."/custom/config/permissions.php";
    require $ROOT_APP."/utility/permissions.php";

    require $ROOT_APP."/database/connection.php";
    
    require $ROOT_APP."/function/function.php";

    if (isset($BACKEND) && $BACKEND) { require $ROOT_APP."/function/backend/function.php"; }
    if (isset($FRONTEND) && $FRONTEND) { require $ROOT_APP."/function/frontend/function.php"; }
    
    require $ROOT."/custom/function/function.php";

    $PAGE = infoPage();
    $SOCIETY = infoSociety();

    require $ROOT_APP."/utility/authorize.php";

    if (isset($BACKEND) && $BACKEND) { require $ROOT_APP."/utility/backend/set-up.php"; }
    if (isset($FRONTEND) && $FRONTEND) { require $ROOT_APP."/utility/frontend/set-up.php"; }

?>