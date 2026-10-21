<?php

    # Imposto la durata delle sessioni in secondi
        ini_set('session.gc_maxlifetime', 14400); # 4 Ore
        session_set_cookie_params(\Wonder\Http\Cookie::sessionOptions(14400)); # 4 Ore, SameSite=Lax, HttpOnly, Secure su HTTPS

    # Registro tutti gli errori; via web a schermo solo con debug (APP_DEBUG o APP_ENV=local)
        error_reporting(E_ALL);
        if (PHP_SAPI !== 'cli') {
            ini_set('display_errors', \Wonder\App\Debug::enabled() ? '1' : '0');
        }
        ini_set('log_errors', '1');

    # Niente versione di PHP negli header (expose_php si cambia solo da php.ini)
        header_remove('X-Powered-By');

    # Tolgo gli errori mysqli
        mysqli_report(MYSQLI_REPORT_OFF);
    
    # Imposto il timing basato su Roma
        date_default_timezone_set('Europe/Rome');

    # Inizio la sessione
        session_start();