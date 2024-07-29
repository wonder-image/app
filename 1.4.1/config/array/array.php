<?php

    $SEO = (object) array();
    $DB = (object) array();
    $MAIL = (object) array();
    $COLOR = (object) array();
    $FONT = (object) array();
    $PATH = (object) array();
    $SOCIETY = (object) array();
    $TABLE = (object) array();
    $ANALYTICS = (object) array();
    $API = (object) array();
    $DEFAULT = (object) array();

    require_once $ROOT_APP."/config/array/alert.php"; # Tutte le allerte
    require_once $ROOT_APP."/config/array/characters.php"; # Caratteri speciali
    require_once $ROOT_APP."/config/array/fpdf.php"; # Font aggiuntivi FPDF
    require_once $ROOT_APP."/config/array/env.php"; # Utilizzo il file .env
    require_once $ROOT_APP."/config/array/path.php"; # Imposta tutti i link utili