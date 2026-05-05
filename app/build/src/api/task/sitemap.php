<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $DIR = __DIR__;
    $ROOT = str_replace("/api/task", "", $DIR);
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    include dirname($ROOT_APP)."/vendor-static/xml-sitemaps/runcrawl.php";
    