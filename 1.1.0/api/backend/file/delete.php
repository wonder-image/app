<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    if ($_POST['post']) {

        $folder = $_GET['folder'];
        $table = $_GET['table'];
        $column = $_GET['column'];
        $rowId = $_GET['row_id'];
        $fileId = $_GET['file_id'];

        $files = json_decode(sqlSelect($table, ['id' => $rowId], 1)->row[$column], true);

        $NEW_ARRAY = [];

        foreach ($files as $id => $image) { 

            if ($id != $fileId) { 

                array_push($NEW_ARRAY, $image); 

            } else {

                // Delete image
                $t = strtoupper($table);
                $TABLE = $TABLE->$t[$column]['input'];

                $dir = isset($TABLE['format']['dir']) ? $TABLE['format']['dir'] : '/'; 

                if (substr($dir, -1) != '/') {
                    $extension = pathinfo($image, PATHINFO_EXTENSION);
                    $link = $PATH->rUpload.'/'.$folder.$dir.'.'.$extension;
                } else {
                    $link = $PATH->rUpload.'/'.$folder.$dir.$image;
                }

                unlink($link);

            }

        }

        $JSON_ARRAY = json_encode($NEW_ARRAY);
        
        sqlModify($table, [$column => $JSON_ARRAY], 'id', $rowId);
        
    }

?>