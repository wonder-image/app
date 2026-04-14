<?php

    # Imposto la durata delle sessioni in secondi
        ini_set('session.gc_maxlifetime', 14400); # 4 Ore
        session_set_cookie_params(14400); # 4 Ore

    # Mostro tutti gli errori
        error_reporting(E_ALL);

    # Tolgo gli errori mysqli
        mysqli_report(MYSQLI_REPORT_OFF);
    
    # Imposto il timing basato su Roma
        date_default_timezone_set('Europe/Rome');

    # Inizio la sessione
        session_start();