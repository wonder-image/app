<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = "../../..";
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    header("Location: $ROOT_APP/generator/stats/?frequency=daily");

?>