<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    for ($i=0; $i <= 12; $i++) { 
        echo ".p-$i { padding: calc(var(--spacer) * $i) !important; box-sizing: border-box !important; }";
        echo ".ph-$i { padding-top: calc(var(--spacer) * $i) !important; padding-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".pw-$i { padding-left: calc(var(--spacer) * $i) !important; padding-right: calc(var(--spacer) * $i) !important; }";
        echo ".pt-$i { padding-top: calc(var(--spacer) * $i) !important; }";
        echo ".pr-$i { padding-right: calc(var(--spacer) * $i) !important; }";
        echo ".pb-$i { padding-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".pl-$i { padding-left: calc(var(--spacer) * $i) !important; }";
        echo "@media (max-width: 1000px) {";
        echo ".p-t-$i { padding: calc(var(--spacer) * $i) !important; }";
        echo ".ph-t-$i { padding-top: calc(var(--spacer) * $i) !important; padding-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".pw-t-$i { padding-left: calc(var(--spacer) * $i) !important; padding-right: calc(var(--spacer) * $i) !important; }";
        echo ".pt-t-$i { padding-top: calc(var(--spacer) * $i) !important; }";
        echo ".pr-t-$i { padding-right: calc(var(--spacer) * $i) !important; }";
        echo ".pb-t-$i { padding-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".pl-t-$i { padding-left: calc(var(--spacer) * $i) !important; }";
        echo "}";
        echo "@media (max-width: 768px) {";
        echo ".p-p-$i { padding: calc(var(--spacer) * $i) !important; }";
        echo ".ph-p-$i { padding-top: calc(var(--spacer) * $i) !important; padding-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".pw-p-$i { padding-left: calc(var(--spacer) * $i) !important; padding-right: calc(var(--spacer) * $i) !important; }";
        echo ".pt-p-$i { padding-top: calc(var(--spacer) * $i) !important; }";
        echo ".pr-p-$i { padding-right: calc(var(--spacer) * $i) !important; }";
        echo ".pb-p-$i { padding-bottom: calc(var(--spacer) * $i) !important; }";
        echo ".pl-p-$i { padding-left: calc(var(--spacer) * $i) !important; }";
        echo "}";
    }

?>