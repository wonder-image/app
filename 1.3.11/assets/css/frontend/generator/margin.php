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
        
        $PC .= ".m-$i { margin: calc(var(--spacer) * $i) !important; }";
        $PC .= ".mh-$i { margin: calc(var(--spacer) * $i) 0px !important; }";
        $PC .= ".mw-$i { margin: 0px calc(var(--spacer) * $i) !important; }";
        $PC .= ".mt-$i { margin-top: calc(var(--spacer) * $i) !important; }";
        $PC .= ".mr-$i { margin-right: calc(var(--spacer) * $i) !important; }";
        $PC .= ".mb-$i { margin-bottom: calc(var(--spacer) * $i) !important; }";
        $PC .= ".ml-$i { margin-left: calc(var(--spacer) * $i) !important; }";

        $TABLET .= ".m-t-$i { margin: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".mh-t-$i { margin: calc(var(--spacer) * $i) 0px !important; }";
        $TABLET .= ".mw-t-$i { margin: 0px calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".mt-t-$i { margin-top: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".mr-t-$i { margin-right: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".mb-t-$i { margin-bottom: calc(var(--spacer) * $i) !important; }";
        $TABLET .= ".ml-t-$i { margin-left: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".m-p-$i { margin: calc(var(--spacer) * $i) !important; }";

        $PHONE .= ".mh-p-$i { margin: calc(var(--spacer) * $i) 0px !important; }";
        $PHONE .= ".mw-p-$i { margin: 0px calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".mt-p-$i { margin-top: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".mr-p-$i { margin-right: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".mb-p-$i { margin-bottom: calc(var(--spacer) * $i) !important; }";
        $PHONE .= ".ml-p-$i { margin-left: calc(var(--spacer) * $i) !important; }";

    }

    echo "$PC";
    echo "@media (max-width: 1000px) { $TABLET }";
    echo "@media (max-width: 768px) { $PHONE }";

?>