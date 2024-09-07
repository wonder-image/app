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

    require_once __DIR__."/alert.php"; # Tutte le allerte
    require_once __DIR__."/characters.php"; # Caratteri speciali
    require_once __DIR__."/fpdf.php"; # Font aggiuntivi FPDF
    require_once __DIR__."/env.php"; # Utilizzo il file .env
    require_once __DIR__."/path.php"; # Imposta tutti i link utili