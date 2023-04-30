<?php

    session_start();

    # TODO Da sviluppare 
    // header(
    //     "Content-Security-Policy:".
    //     "default-src 'self' https://wonderimage.it;".
    //     "script-src 'self' 'unsafe-hashes' https://wonderimage.it https://code.jquery.com https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://vjs.zencdn.net https://cdn.datatables.net;".
    //     "style-src 'self' https://wonderimage.it https://cdnjs.cloudflare.com https://cdn.jsdelivr.net https://unpkg.com https://fonts.googleapis.com https://fonts.gstatic.com;".
    //     "img-src 'self' https://wonderimage.it;".
    //     "font-src 'self' https://wonderimage.it https://fonts.googleapis.com https://fonts.gstatic.com https://cdn.jsdelivr.net;".
    //     "object-src 'none'"
    // );

    if (!isset($_SESSION['user_id'])) { $_SESSION['user_id'] = null; }

    $APP_VERSION = "1.1.5";
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