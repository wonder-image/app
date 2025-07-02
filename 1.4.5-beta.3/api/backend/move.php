<?php
    
    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post']) {
        
        $table = $_GET['table'];
        $database = isset($_GET['database']) && !empty($_GET['database']) ? $_GET['database'] : 'main';

        if ($database != 'main') { $mysqli = $MYSQLI_CONNECTION[$database]; }

        $rowId = $_GET['id'];
        $action = $_GET['action'];
        
        $filter = isset($_GET['filter']) ? $_GET['filter'] : null;
        $filterId = isset($_GET['filter_id']) ? $_GET['filter_id'] : null;

        $query = sqlSelect($table, ['id' => $rowId], 1);
        $oldPosition = $query->row['position'];
        
        if ($filter == null && $filterId == null) {
            $TB = strtoupper($table);
            $filter = $TABLE->$TB['position']['input']['filter'] ?? null;
            $filterId = $filter ? $query->row[$filter] : null;
        }

        $newPosition = ($action == 'up') ? $oldPosition - 1 : $oldPosition + 1;

        if ($filter != null && $filterId != null) {
            $oldPositionId = sqlSelect($table, [ 'position' => $newPosition, $filter => $filterId ], 1)->id;
            sqlModify($table, [ 'position' => $oldPosition ], 'id', $oldPositionId);
        } else {
            sqlModify($table, [ 'position' => $oldPosition ], 'position', $newPosition);
        }

        sqlModify($table, ['position' => $newPosition], 'id', $rowId);

    }