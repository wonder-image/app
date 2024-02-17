<?php

    # Imposto la durata delle sessioni in secondi
        ini_set('session.gc_maxlifetime', 14400); # 4 Ore
        session_set_cookie_params(14400); # 4 Ore

    # Mostro tutti gli errori
        error_reporting(E_ALL);
    
    # Imposto il timing basato su Roma
        date_default_timezone_set('Europe/Rome');

    # Inizio la sessione
        session_start();

    # Imposto la sessione con utente non definito se non è impostato
        if (!isset($_SESSION['user_id'])) { $_SESSION['user_id'] = null; }

    
    $APP_VERSION = "1.3.5";
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