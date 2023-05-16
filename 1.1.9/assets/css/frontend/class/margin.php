<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    for ($i=0; $i <= 12; $i++) { 
        echo ".m-$i { margin: calc(var(--spacer) * $i) !important; }";
        echo ".mh-$i { margin: calc(var(--spacer) * $i) 0px !important; }";
        echo ".mw-$i { margin: 0px calc(var(--spacer) * $i) !important; }";
        echo ".mt-$i { margin-top: calc(var(--spacer) * $i) !important; }";
        echo ".mr-$i { margin-right: calc(var(--spacer) * $i) !important; }";
        echo ".mb-$i { margin-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".ml-$i { margin-left: calc(var(--spacer) * $i) !important; }";
        echo "@media (max-width: 1000px) {";
        echo ".m-t-$i { margin: calc(var(--spacer) * $i) !important; }";
        echo ".mh-t-$i { margin: calc(var(--spacer) * $i) 0px !important; }";
        echo ".mw-t-$i { margin: 0px calc(var(--spacer) * $i) !important; }";
        echo ".mt-t-$i { margin-top: calc(var(--spacer) * $i) !important; }";
        echo ".mr-t-$i { margin-right: calc(var(--spacer) * $i) !important; }";
        echo ".mb-t-$i { margin-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".ml-t-$i { margin-left: calc(var(--spacer) * $i) !important; }";
        echo "}";
        echo "@media (max-width: 768px) {";
        echo ".m-p-$i { margin: calc(var(--spacer) * $i) !important; }";
        echo ".mh-p-$i { margin: calc(var(--spacer) * $i) 0px !important; }";
        echo ".mw-p-$i { margin: 0px calc(var(--spacer) * $i) !important; }";
        echo ".mt-p-$i { margin-top: calc(var(--spacer) * $i) !important; }";
        echo ".mr-p-$i { margin-right: calc(var(--spacer) * $i) !important; }";
        echo ".mb-p-$i { margin-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".ml-p-$i { margin-left: calc(var(--spacer) * $i) !important; }";
        echo "}";
    }

?>