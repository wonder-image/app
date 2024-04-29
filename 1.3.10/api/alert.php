<?php

    header('Access-Control-Allow-Origin: *');
    
    if (isset($_POST['backend'])) { $BACKEND = true; }
    if (isset($_POST['frontend'])) { $FRONTEND = true; }

    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post'] && !empty($_POST['alert'])) { 
        echo alertTheme(
            $_POST['alert'],
            isset($_POST['alertType']) ? $_POST['alertType'] : null,
            isset($_POST['alertTitle']) ? $_POST['alertTitle'] : null,
            isset($_POST['alertText']) ? $_POST['alertText'] : null,
        ); 
    }