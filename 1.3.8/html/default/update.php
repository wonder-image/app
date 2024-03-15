<?php

    # Aggiorno le tabelle

        foreach ($TABLE as $table => $value) {
            
            $table_name = strtolower($table);
            $table_column = $value;

            sqlTable($table_name, $table_column);
            
        }

        echo "Tabelle aggiornate<br>";

    # Aggiorno le righe

        $files = empty(scandir("$ROOT_APP/build/row/")) ? [] : scandir("$ROOT_APP/build/row/");

        foreach ($files as $file) {
            if ($file != '' && $file != '.' && $file != '..') {
                include "$ROOT_APP/build/row/$file";
            }
        }

        $files = empty(scandir("$ROOT/custom/build/row/")) ? [] : scandir("$ROOT/custom/build/row/");

        foreach ($files as $file) {
            if ($file != '' && $file != '.' && $file != '..') {
                include "$ROOT/custom/build/row/$file";
            }
        }

        echo "Righe tabelle aggiornate<br>";

    # Aggiungo pagine

        $files = empty(scandir("$ROOT_APP/build/page/")) ? [] : scandir("$ROOT_APP/build/page/");

        foreach ($files as $file) {
            if ($file != '' && $file != '.' && $file != '..') {
                include "$ROOT_APP/build/page/$file";
            }
        }

        $files = empty(scandir("$ROOT/custom/build/page/")) ? [] : scandir("$ROOT/custom/build/page/");

        foreach ($files as $file) {
            if ($file != '' && $file != '.' && $file != '..') {
                include "$ROOT/custom/build/page/$file";
            }
        }

        echo "Pagine aggiunte<br>";
        echo "<br><b>Aggiornamento completato!</b>";
    
?>