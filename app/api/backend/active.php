<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post']) {
        
        $id = $_GET['id'];
        $table = $_GET['table'];
        $database = isset($_GET['database']) && !empty($_GET['database']) ? $_GET['database'] : 'main';

        if ($database != 'main') { $mysqli = $MYSQLI_CONNECTION[$database]; }

        $row = sqlSelect($table, ['id' => $id], 1)->row;

        $active = ($row['active'] == 'true') ? 'false' : 'true';

        sqlModify($table, ['active' => $active], 'id', $id);

    }