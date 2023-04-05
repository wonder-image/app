<?php

    $PRIVATE = true;
        
    $BACKEND = true;
    $PERMIT = [];
    
    $ROOT = $_SERVER['DOCUMENT_ROOT'].'/';

    include $ROOT.'app/wonder-image.php';

    if ($_POST['post']) {

        $table = $_POST['table'];
        $column = $_POST['column'];
        $rowId = $_POST['row_id'];
        $oldId = $_POST['file_id'];
        $action = $_POST['action'];

        $files = json_decode(sqlSelect($table, ['id' => $rowId], 1)->row[$column], true);

        $newId = ($action == 'up') ? $oldId-- : $oldId++;

        $NEW_ARRAY = [];

        foreach ($files as $id => $image) {
            
            if ($id == $newId) {
                $NEW_ARRAY[$oldId] = $image;
            } elseif ($id == $oldId) {
                $NEW_ARRAY[$newId] = $image;
            } else {
                $NEW_ARRAY[$id] = $image;
            }

        }

        ksort($NEW_ARRAY, SORT_NUMERIC);

        $JSON_ARRAY = json_encode($NEW_ARRAY);
        
        sqlModify($table, [$column => $JSON_ARRAY], 'id', $rowId);
        
    }

?>