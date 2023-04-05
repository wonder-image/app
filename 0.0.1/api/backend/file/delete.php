<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post']) {

        $table = $_GET['table'];
        $column = $_GET['column'];
        $rowId = $_GET['row_id'];
        $fileId = $_GET['file_id'];

        $files = json_decode(sqlSelect($table, ['id' => $rowId], 1)->row[$column], true);

        $NEW_ARRAY = [];

        foreach ($files as $id => $image) {
            if ($id != $fileId) { array_push($NEW_ARRAY, $image); }
        }

        $JSON_ARRAY = json_encode($NEW_ARRAY);
        
        sqlModify($table, [$column => $JSON_ARRAY], 'id', $rowId);
        
    }

?>