<?php

    $files = empty(scandir("$ROOT_APP/build/table/")) ? [] : scandir("$ROOT_APP/build/table/");

    foreach ($files as $file) {
        if ($file != '' && $file != '.' && $file != '..') {
            if (isset(pathinfo($file)['extension']) && pathinfo($file)['extension'] == 'php') {
                require_once "$ROOT_APP/build/table/$file";
            }
        }
    }

    $files = empty(scandir("$ROOT/custom/build/table/")) ? [] : scandir("$ROOT/custom/build/table/");

    foreach ($files as $file) {
        if ($file != '' && $file != '.' && $file != '..') {
            if (isset(pathinfo($file)['extension']) && pathinfo($file)['extension'] == 'php') {
                require_once "$ROOT/custom/build/table/$file";
            }
        }
    }