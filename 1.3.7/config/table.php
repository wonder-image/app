<?php

    $files = empty(scandir("$ROOT_APP/build/table/")) ? [] : scandir("$ROOT_APP/build/table/");

    foreach ($files as $file) {
        if ($file != '' && $file != '.' && $file != '..') {
            require_once "$ROOT_APP/build/table/$file";
        }
    }

    $files = empty(scandir("$ROOT/custom/build/table/")) ? [] : scandir("$ROOT/custom/build/table/");

    foreach ($files as $file) {
        if ($file != '' && $file != '.' && $file != '..') {
            require_once "$ROOT/custom/build/table/$file";
        }
    }

?>