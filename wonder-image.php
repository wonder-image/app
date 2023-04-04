<?php

    ini_set ('display_errors', 1);
    ini_set ('display_startup_errors', 1);
    ini_set ('session.autostart', 1);
    mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);
    error_reporting (E_ALL);

    session_start();

    $VERSION = "0.0.2";
    $ROOT_APP = "$ROOT/app/$VERSION";

    include $ROOT_APP."/utility/array.php";
    include $ROOT_APP."/utility/alert.php";

    include $ROOT."/custom/config/config.php";

    include $ROOT."/custom/config/permissions.php";
    include $ROOT_APP."/utility/permissions.php";

    include $ROOT_APP."/database/connection.php";
    
    include $ROOT_APP."/function/function.php";

    if (isset($BACKEND) && $BACKEND) {
        include $ROOT_APP.'/function/backend/function.php';
    }

    if (isset($FRONTEND) && $FRONTEND) {
        include $ROOT_APP.'/function/frontend/function.php';
    }
    
    include $ROOT."/custom/function/function.php";

    include $ROOT_APP."/build/build/table.php";
    include $ROOT_APP."/build/build/row.php";

    $PAGE = infoPage();
    $SOCIETY = infoSociety();

    include $ROOT_APP."/utility/authorize.php";

    if (isset($BACKEND) && $BACKEND) {
        include $ROOT_APP.'/utility/backend/set-up.php';
    }

    if (isset($FRONTEND) && $FRONTEND) {
        include $ROOT_APP.'/utility/frontend/set-up.php';
    }

?>