<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Content-type: text/css");

    for ($i=0; $i <= 130; $i + 5) { 
        echo ".w-$i { width: $i% !important; }";
        echo ".max-w-$i { max-width: $i% !important; }";
        echo ".h-$i { height: $i% !important; }";
        echo ".max-h-$i { max-height: $i% !important; }";
        echo "@media (max-width: 1000px) {";
        echo ".w-t-$i { width: $i% !important; }";
        echo ".max-w-t-$i { max-width: $i% !important; }";
        echo ".h-t-$i { height: $i% !important; }";
        echo ".max-h-t-$i { max-height: $i% !important; }";
        echo "}";
        echo "@media (max-width: 768px) {";
        echo ".w-p-$i { width: $i% !important; }";
        echo ".max-w-p-$i { max-width: $i% !important; }";
        echo ".h-p-$i { height: $i% !important; }";
        echo ".max-h-p-$i { max-height: $i% !important; }";
        echo "}";
    }

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