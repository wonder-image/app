<?php

    $APP_VERSION = "2.0.0";
    $ROOT_APP = __DIR__."/app";
    $ROOT_RESOURCES = __DIR__."/resources";

    require_once $ROOT."/vendor/autoload.php";

    require_once $ROOT_APP."/function/function.php";
    
    require_once $ROOT_APP."/config/config.php";

    $syncRuntimeGlobals = static function (array $runtimeContext): void {
        foreach ([
            'ROOT',
            'APP_VERSION',
            'ROOT_APP',
            'ROOT_RESOURCES',
            'SEO',
            'DB',
            'MAIL',
            'COLOR',
            'FONT',
            'PATH',
            'SOCIETY',
            'TABLE',
            'ANALYTICS',
            'API',
            'DEFAULT',
            'PAGE',
            'PERMITS',
            'MYSQLI_CONNECTION',
            'mysqli',
            'BACKEND',
            'FRONTEND',
            'PRIVATE',
            'PERMIT',
            'ROUTE_PARAMETERS',
            'ROUTE_META',
            'ALERT',
            'ERROR',
            'USER',
        ] as $runtimeVariable) {
            if (array_key_exists($runtimeVariable, $runtimeContext)) {
                $GLOBALS[$runtimeVariable] = $runtimeContext[$runtimeVariable];
            }
        }
    };

    $syncRuntimeGlobals(get_defined_vars());
    
    require_once $ROOT_APP."/service/service.php";

    require_once $ROOT_APP."/middleware/middleware.php";

    if (isset($BACKEND) && $BACKEND) { require_once $ROOT_APP."/utility/backend/set-up.php"; }

    if (isset($FRONTEND) && $FRONTEND) { require_once $ROOT_APP."/utility/frontend/set-up.php"; }

    $syncRuntimeGlobals(get_defined_vars());
