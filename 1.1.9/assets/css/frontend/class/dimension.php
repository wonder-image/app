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

        $PC .= ".w-$i { width: $i% !important; }";
        $PC .= ".max-w-$i { max-width: $i% !important; }";
        $PC .= ".h-$i { height: $i% !important; }";
        $PC .= ".max-h-$i { max-height: $i% !important; }";

        $TABLET .= ".w-t-$i { width: $i% !important; }";
        $TABLET .= ".max-w-t-$i { max-width: $i% !important; }";
        $TABLET .= ".h-t-$i { height: $i% !important; }";
        $TABLET .= ".max-h-t-$i { max-height: $i% !important; }";

        $PHONE .= ".w-p-$i { width: $i% !important; }";
        $PHONE .= ".max-w-p-$i { max-width: $i% !important; }";
        $PHONE .= ".h-p-$i { height: $i% !important; }";
        $PHONE .= ".max-h-p-$i { max-height: $i% !important; }";
        
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