<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $DIR = __DIR__;
    $ROOT = str_replace("/api/task/stats", "", $DIR);
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $FREQUENCY = "monthly";
    require_once $ROOT_APP."/generator/stats/index.php";

?>