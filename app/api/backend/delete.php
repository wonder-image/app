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

	        $position = null;
	        $filter = null;
	        $filterId = null;

	        if (sqlColumnExists($table, 'position')) {

	            $query = sqlSelect($table, ['id' => $id], 1);

	            if (!($query->exists ?? false)) {
	                exit;
	            }

	            $position = $query->row['position'] ?? null;

	            $TB = strtoupper($table);
	            $filter = $TABLE->$TB['position']['input']['filter'] ?? null;
	            $filterId = $filter ? ($query->row[$filter] ?? null) : null;

	        }

	        if (sqlColumnExists($table, 'active')) {
	            
	            $values = [
                "active" => "false",
                "deleted" => "true"
            ];
        
        } elseif (sqlColumnExists($table, 'visible')) {
            
            $values = [
                "visible" => "false",
                "deleted" => "true"
            ];

        } else {

            $values = [
                "deleted" => "true"
            ];

        }

	        $sql = sqlModify($table, $values, 'id', $id);

	        if ($position !== null && sqlColumnExists($table, 'position')) {

	            if ($filter != null && $filterId != null) {
	                $sql = sqlSelect($table, [ $filter => $filterId, 'deleted' => 'false' ]);
	            } else {
	                $sql = sqlSelect($table, [ 'deleted' => 'false' ]);
	            }

	            foreach ($sql->row as $key => $row) {

	                if (($row['position'] ?? null) === null || $row['position'] <= $position) {
	                    continue;
	                }

	                $pos = $row['position'] - 1;
	                sqlModify($table, ['position' => $pos], 'id', $row['id']);

	            }

        }

    }
