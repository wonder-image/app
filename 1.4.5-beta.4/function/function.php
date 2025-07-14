<?php

    require_once __DIR__."/utility.php";
    require_once __DIR__."/arrayTo.php";
    require_once __DIR__."/sql.php";
    require_once __DIR__."/info.php";
    require_once __DIR__."/mail.php";
    require_once __DIR__.'/user.php';

    require_once __DIR__."/other/function.php";
    require_once __DIR__."/file/function.php";
    require_once __DIR__."/string/function.php";

    if (isset($BACKEND) && $BACKEND) { require_once __DIR__."/backend/function.php"; }
    if (isset($FRONTEND) && $FRONTEND) { require_once __DIR__."/frontend/function.php"; }
    
    # Funzioni CUSTOM
    require_once $ROOT."/custom/function/function.php";