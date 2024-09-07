<?php

    $files = scanParentDir("$ROOT_APP/build/table/");

    foreach ($files as $file) {
        if (isset(pathinfo($file)['extension']) && pathinfo($file)['extension'] == 'php') {
            require_once "$ROOT_APP/build/table/$file";
        }
    }

    $files = scanParentDir("$ROOT/custom/build/table/");

    foreach ($files as $file) {
        if (isset(pathinfo($file)['extension']) && pathinfo($file)['extension'] == 'php') {
            require_once "$ROOT/custom/build/table/$file";
        }
    }