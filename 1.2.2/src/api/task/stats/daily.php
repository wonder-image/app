<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = "../../..";
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $FREQUENCY = "daily";
    require_once $ROOT_APP."/generator/stats/index.php";

?>