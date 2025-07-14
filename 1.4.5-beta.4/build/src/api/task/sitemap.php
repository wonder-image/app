<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $DIR = __DIR__;
    $ROOT = str_replace("/api/task", "", $DIR);
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    include $ROOT_APP."/generator/sitemap/runcrawl.php";
    