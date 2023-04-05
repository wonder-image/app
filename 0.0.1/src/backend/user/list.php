<?php

    $BACKEND = true;
    $PERMIT = ['admin'];
    $PRIVATE = true;

    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    include $ROOT.'/app/wonder-image.php';
    include "set-up.php";
    include $ROOT_APP."/html/backend/list.php";
    
?>