<?php

    require_once __DIR__."/utility.php";
    require_once __DIR__."/helper.php";
    require_once __DIR__."/arrayTo.php";
    require_once __DIR__."/sql.php";
    require_once __DIR__."/info.php";
    require_once __DIR__."/mail.php";

    require_once __DIR__.'/user/function.php';
    require_once __DIR__.'/consent/function.php';
    require_once __DIR__."/other/function.php";
    require_once __DIR__."/file/function.php";
    require_once __DIR__."/string/function.php";
    require_once __DIR__."/components/function.php";

    if (isset($BACKEND) && $BACKEND) { require_once __DIR__."/backend/function.php"; }
    if (isset($FRONTEND) && $FRONTEND) { require_once __DIR__."/frontend/function.php"; }
    
    # Funzioni CUSTOM
    $customFunctionFile = $ROOT."/custom/function/function.php";
    if (file_exists($customFunctionFile)) {
        require_once $customFunctionFile;
    }
