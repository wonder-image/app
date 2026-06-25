<?php

    $componentFiles = glob(__DIR__.'/*.php') ?: [];

    sort($componentFiles);

    foreach ($componentFiles as $componentFile) {
        if (basename($componentFile) === 'function.php') {
            continue;
        }

        require_once $componentFile;
    }
