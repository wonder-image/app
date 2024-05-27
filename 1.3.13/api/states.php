<?php

    header('Access-Control-Allow-Origin: *');
    
    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post'] && !empty($_POST['country'])) { echo json_encode(states($_POST['country']), true); }