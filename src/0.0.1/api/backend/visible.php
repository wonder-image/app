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

        $visible = ($row['visible'] == 'true') ? 'false' : 'true';

        sqlModify($table, ['visible' => $visible], 'id', $id);

    }

?>