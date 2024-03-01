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

    for ($i=0; $i <= 150; $i+=5) { 

        # Percentuale
        $PC .= ".w-$i { width: $i% !important; }";
        $PC .= ".max-w-$i { max-width: $i% !important; }";
        $PC .= ".min-w-$i { min-width: $i% !important; }";
        $PC .= ".h-$i { height: $i% !important; }";
        $PC .= ".max-h-$i { max-height: $i% !important; }";
        $PC .= ".min-h-$i { min-height: $i% !important; }";

        # Viewport
        $PC .= ".vw-$i { width: {$i}vw !important; }";
        $PC .= ".max-vw-$i { max-width: {$i}vw !important; }";
        $PC .= ".min-vw-$i { min-width: {$i}vw !important; }";
        $PC .= ".vh-$i { height: {$i}vh !important; }";
        $PC .= ".max-vh-$i { max-height: {$i}vh !important; }";
        $PC .= ".min-vh-$i { min-height: {$i}vh !important; }";

        # Percentuale
        $TABLET .= ".w-t-$i { width: $i% !important; }";
        $TABLET .= ".max-w-t-$i { max-width: $i% !important; }";
        $TABLET .= ".min-w-t-$i { min-width: $i% !important; }";
        $TABLET .= ".h-t-$i { height: $i% !important; }";
        $TABLET .= ".max-h-t-$i { max-height: $i% !important; }";
        $TABLET .= ".min-h-t-$i { min-height: $i% !important; }";

        # Viewport
        $TABLET .= ".vw-t-$i { width: {$i}vw !important; }";
        $TABLET .= ".max-vw-t-$i { max-width: {$i}vw !important; }";
        $TABLET .= ".min-vw-t-$i { min-width: {$i}vw !important; }";
        $TABLET .= ".vh-t-$i { height: {$i}vh !important; }";
        $TABLET .= ".max-vh-t-$i { max-height: {$i}vh !important; }";
        $TABLET .= ".min-vh-t-$i { min-height: {$i}vh !important; }";

        # Percentuale
        $PHONE .= ".w-p-$i { width: $i% !important; }";
        $PHONE .= ".max-w-p-$i { max-width: $i% !important; }";
        $PHONE .= ".min-w-p-$i { min-width: $i% !important; }";
        $PHONE .= ".h-p-$i { height: $i% !important; }";
        $PHONE .= ".max-h-p-$i { max-height: $i% !important; }";
        $PHONE .= ".min-h-p-$i { min-height: $i% !important; }";

        # Viewport
        $PHONE .= ".vw-p-$i { width: {$i}vw !important; }";
        $PHONE .= ".max-vw-p-$i { max-width: {$i}vw !important; }";
        $PHONE .= ".min-vw-p-$i { min-width: {$i}vw !important; }";
        $PHONE .= ".vh-p-$i { height: {$i}vh !important; }";
        $PHONE .= ".max-vh-p-$i { max-height: {$i}vh !important; }";
        $PHONE .= ".min-vh-p-$i { min-height: {$i}vh !important; }";
        
    }

    echo "$PC";
    echo "@media (max-width: 1000px) { $TABLET }";
    echo "@media (max-width: 768px) { $PHONE }";

?>
.w-auto { width: auto !important; }
.w-fit { width: fit-content !important; }

.h-auto { height: auto !important; }
.h-fit { height: fit-content !important; }

@media (max-width: 1000px) {
.w-t-auto { width: auto !important; }
.w-t-fit { width: fit-content !important; }

.h-t-auto { height: auto !important; }
.h-t-fit { height: fit-content !important; }
}

@media (max-width: 768px) {
.w-p-auto { width: auto !important; }
.w-p-fit { width: fit-content !important; }

.h-p-auto { height: auto !important; }
.h-p-fit { height: fit-content !important; }
}