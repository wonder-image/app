<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $DIR = __DIR__;
    $ROOT = str_replace("/api/task", "", $DIR);
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $FREQUENCY = "daily";
    require_once $ROOT_APP."/generator/stats/index.php";

?>