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

        if (sqlColumnExists($table, 'position')) {

            $position = sqlSelect($table, ['id' => $id], 1)->row['position'];
            

            $sql = sqlSelect($table, "`position` > $position AND `deleted` = 'false'");

            foreach ($sql->row as $key => $row) {

                $pos = $row['position'] - 1;
                sqlModify($table, ['position' => $pos], 'id', $row['id']);

            }

        }

    }