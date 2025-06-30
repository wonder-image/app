<?php

    $APP_VERSION = "1.4.5-beta.2";
    $LIB_VERSION = "1.9.5-beta.2";
    $ROOT_APP = __DIR__."/$APP_VERSION";

    require_once $ROOT."/vendor/autoload.php";

    require_once $ROOT_APP."/function/function.php";
    
    require_once $ROOT_APP."/config/config.php";

    require_once $ROOT_APP."/middleware/middleware.php";

    if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP."/utility/backend/set-up.php"; }
    
    if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP."/utility/frontend/set-up.php"; }
