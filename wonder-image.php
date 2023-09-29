<?php

    session_start();

    error_reporting(E_ALL);
    date_default_timezone_set('Europe/Rome');

    if (!isset($_SESSION['user_id'])) { $_SESSION['user_id'] = null; }

    $APP_VERSION = "1.2.6";
    $ROOT_APP = __DIR__."/$APP_VERSION";

    require_once $ROOT."/vendor/autoload.php";

    require_once $ROOT_APP."/config/config.php";

    require_once $ROOT_APP."/database/connection.php";
    
    require_once $ROOT_APP."/function/function.php";

    $PAGE = infoPage();
    if (sqlTableExists('society')) { $SOCIETY = infoSociety(); }

    require_once $ROOT_APP."/utility/authorize.php";

    if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP."/utility/backend/set-up.php"; }
    if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP."/utility/frontend/set-up.php"; }

?>