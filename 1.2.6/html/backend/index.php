<?php

    $ALERT = '';

    if (!isset($PAGE_TABLE)) {
        $table = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$table;
    }
    
    if (!empty($_GET['modify'])) {
        
        $TITLE = "Modifica $TEXT->titleS";

        $SQL = sqlSelect($NAME->table, ['id' => $_GET['modify']], 1);
        $VALUES = $SQL->row;

    } else {

        $TITLE = "Aggiungi $TEXT->titleS";
        $VALUES = [];

    }

    if (!empty($PAGE->redirect)) {
        $REDIRECT = $PAGE->redirect;
    } else {
        $REDIRECT = "$PATH->backend/$NAME->folder/list.php";
    }

    if (isset($_POST['upload']) || isset($_POST['upload-add'])) {

        $POST = array_merge($_POST, $_FILES);
        $VALUES = formToArray($NAME->table, $POST, $PAGE_TABLE, isset($VALUES) ? $VALUES : null);
        
        if (empty($ALERT)) {
            if (!empty($_GET['modify'])) {

                sqlModify($NAME->table, $VALUES, 'id', $_GET['modify']);

            } else {
        
                sqlInsert($NAME->table, $VALUES);
        
            }
        }

        if (empty($ALERT)) {
            if (isset($_POST['upload-add'])) {
                header("Location: $PATH->backend/$NAME->folder/index.php?redirect=$PAGE->redirectBase64");
            } else {
                header("Location: $REDIRECT");
            }
            exit;
        }

    }

?>