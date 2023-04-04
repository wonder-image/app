<?php

    $PRIVATE = false;

    $ROOT = $_SERVER['DOCUMENT_ROOT'].'/';

    include $ROOT.'app/wonder-image.php';

    sqlExport($_GET['table'], $_GET['format']);

?>