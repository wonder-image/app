<?php

    $SEO = (object) [];
    $DB = (object) [];
    $MAIL = (object) [];
    $COLOR = (object) [];
    $FONT = (object) [];
    $PATH = (object) [];
    $SOCIETY = (object) [];
    $TABLE = (object) [];
    $ANALYTICS = (object) [];
    $API = (object) [];
    $DEFAULT = (object) [];

    require_once __DIR__."/alert.php"; # Tutte le allerte
    require_once __DIR__."/characters.php"; # Caratteri speciali
    require_once __DIR__."/fpdf.php"; # Font aggiuntivi FPDF
    require_once __DIR__."/env.php"; # Utilizzo il file .env
    require_once __DIR__."/path.php"; # Imposta tutti i link utili