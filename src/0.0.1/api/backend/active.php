<?php

    $PRIVATE = true;
        
    $BACKEND = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'].'/';

    include $ROOT.'app/wonder-image.php';

    if ($_POST['post']) {
        
        $id = $_GET['id'];
        $table = $_GET['table'];

        $row = sqlSelect($table, ['id' => $id], 1)->row;

        $active = ($row['active'] == 'true') ? 'false' : 'true';

        sqlModify($table, ['active' => $active], 'id', $id);

    }

?>