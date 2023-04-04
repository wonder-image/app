<?php

    $files = empty(scandir("$ROOT_APP/build/table/")) ? [] : scandir("$ROOT_APP/build/table/");

    foreach ($files as $file) {
        if ($file != '' && $file != '.' && $file != '..') {
            include "$ROOT_APP/build/table/$file";
        }
    }

    $files = empty(scandir("$ROOT/custom/build/table/")) ? [] : scandir("$ROOT/custom/build/table/");

    foreach ($files as $file) {
        if ($file != '' && $file != '.' && $file != '..') {
            include "$ROOT/custom/build/table/$file";
        }
    }

    foreach ($TABLE as $table => $value) {
        
        $table_name = strtolower($table);
        $table_column = $value;

        sqlTable($table_name, $table_column);
        
    }
    
?>