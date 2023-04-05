<?php
    
    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post']) {
        
        $table = $_GET['table'];

        $rowId = $_GET['id'];
        $action = $_GET['action'];
        
        $filter = isset($_GET['filter']) ? $_GET['filter'] : null;
        $filterId = isset($_GET['filter_id']) ? $_GET['filter_id'] : null;

        $oldPosition = sqlSelect($table, ['id' => $rowId], 1)->row['position'];
        
        $newPosition = ($action == 'up') ? $oldPosition - 1 : $oldPosition + 1;

        if ($filter != null && $filterId != null) {
            sqlModify($table, ['position' => $oldPosition, $filter => $filterId], 'position', $newPosition);
        } else {
            sqlModify($table, ['position' => $oldPosition], 'position', $newPosition);
        }

        sqlModify($table, ['position' => $newPosition], 'id', $rowId);

    }
    
?>