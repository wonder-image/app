<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    $PC = "";
    $TABLET = "";
    $PHONE = "";

    for ($i=0; $i <= 12; $i++) { 

        $PC .= ".p-$i { padding: calc(var(--spacer) * $i) !important; box-sizing: border-box !important; }";
        $PC .= ".ph-$i { padding-top: calc(var(--spacer) * $i) !important; padding-bottom: calc(var(--spacer) * $i) !important; }";
        $PC .= ".pw-$i { padding-left: calc(var(--spacer) * $i) !important; padding-right: calc(var(--spacer) * $i) !important; }";
        $PC .= ".pt-$i { padding-top: calc(var(--spacer) * $i) !important; }";
        $PC .= ".pr-$i { padding-right: calc(var(--spacer) * $i) !important; }";
        $PC .= ".pb-$i { padding-bottom: calc(var(--spacer) * $i) !important; }";
        $PC .= ".pl-$i { padding-left: calc(var(--spacer) * $i) !important; }";

        $TABLET .= ".p-t-$i { padding: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".ph-t-$i { padding-top: calc(var(--spacer) * $i) !important; padding-bottom: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".pw-t-$i { padding-left: calc(var(--spacer) * $i) !important; padding-right: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".pt-t-$i { padding-top: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".pr-t-$i { padding-right: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".pb-t-$i { padding-bottom: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".pl-t-$i { padding-left: calc(var(--spacer) * $i) !important; }";
        
        $PHONE .= ".p-p-$i { padding: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".ph-p-$i { padding-top: calc(var(--spacer) * $i) !important; padding-bottom: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".pw-p-$i { padding-left: calc(var(--spacer) * $i) !important; padding-right: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".pt-p-$i { padding-top: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".pr-p-$i { padding-right: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".pb-p-$i { padding-bottom: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".pl-p-$i { padding-left: calc(var(--spacer) * $i) !important; }";

    }

    echo "$PC";
    echo "@media (max-width: 1000px) { $TABLET }";
    echo "@media (max-width: 768px) { $PHONE }";

?>