<?php

    $ALERT = '';

    // I pulsanti `submit()`/`submitAdd()` del form del sito emettono `_csrf`.
    \Wonder\Http\Csrf::enableLegacyFormField();

    if (!isset($NAME->database) || $NAME->database == '') { 
        $NAME->database = 'main';
    } else {
        $mysqli = $MYSQLI_CONNECTION[$NAME->database];
    }

    if (!isset($PAGE_TABLE)) {
        $table = strtoupper($NAME->table);
        $PAGE_TABLE = isset($TABLE->$table)
            ? $TABLE->$table
            : \Wonder\App\Table::key((string) $NAME->table)->schema();
    }
    
    if (!empty($_GET['modify']) || !empty($_GET['duplicate'])) {
        
        if (!empty($_GET['modify'])) {
            $TITLE = "Modifica $TEXT->titleS";
            $getId = $_GET['modify'];
        } else {
            $TITLE = "Aggiungi $TEXT->titleS";
            $getId = $_GET['duplicate'];
        }

        $SQL = sqlSelect($NAME->table, [ 'id' => $getId ], 1);
        $VALUES = $SQL->row;

    } else {

        $TITLE = "Aggiungi $TEXT->titleS";
        $VALUES = [];

    }

    $REDIRECT = !empty($PAGE->redirect) ? $PAGE->redirect : "$PATH->backend/$NAME->folder/list.php";

    $UPLOAD_POST = isset($_POST['upload']) || isset($_POST['upload-add']);

    if ($UPLOAD_POST && !\Wonder\Http\Csrf::verify()) {

        // Senza token CSRF valido non scrive: alert nel layout.
        global $ALERT;
        $ALERT = \Wonder\Backend\Support\ResourcePageController::CSRF_ALERT;

    } elseif ($UPLOAD_POST) {

        $POST = array_merge($_POST, $_FILES);
        if (isset($_GET['duplicate'])) { $VALUES = null; }
        $VALUES = formToArray($NAME->table, $POST, $PAGE_TABLE, $VALUES ?? null);
        
        if (empty($ALERT)) {
            if (!empty($_GET['modify']) || !empty($_POST['modify']) ) {

                $MODIFY_ID = empty($_GET['modify']) ? $_POST['modify'] : $_GET['modify'];
                sqlModify($NAME->table, $VALUES, 'id', $MODIFY_ID);

            } else {
        
                sqlInsert($NAME->table, $VALUES);
        
            }
        }

        if (empty($ALERT)) {

            $LOCATION = isset($_POST['upload-add']) ? "index.php?redirect=$PAGE->redirectBase64" : $REDIRECT;
            
            header("Location: $LOCATION");
            exit;

        }

    }
