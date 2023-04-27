<?php

    require_once $ROOT_APP."/function/utility.php";
    require_once $ROOT_APP."/function/arrayTo.php";
    require_once $ROOT_APP."/function/sql.php";
    require_once $ROOT_APP."/function/string.php";
    require_once $ROOT_APP."/function/info.php";
    require_once $ROOT_APP."/function/date.php";
    require_once $ROOT_APP."/function/mail.php";
    require_once $ROOT_APP."/function/geo.php";
    require_once $ROOT_APP."/function/color.php";
    require_once $ROOT_APP.'/function/user.php';

    require_once $ROOT_APP."/function/other/function.php";
    require_once $ROOT_APP."/function/file/function.php";
    require_once $ROOT_APP."/function/password/function.php";

    if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP."/function/backend/function.php"; }
    if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP."/function/frontend/function.php"; }
    
    # Funzioni CUSTOM
    require_once $ROOT."/custom/function/function.php";
    
?>