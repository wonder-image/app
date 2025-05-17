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
                $resize = isset($TABLE['format']['resize']) ? $TABLE['format']['resize'] : ''; 

                if (substr($dir, -1) != '/') {

                    $extension = pathinfo($image, PATHINFO_EXTENSION);

                    $name = explode('/', $dir);
                    $lastKey = array_key_last($name);

                    $path = $PATH->rUpload.'/'.$folder.str_replace($name,'', $dir);
                    $name = $name[$lastKey].'.'.$extension;

                } else {

                    $name = $image;
                    $path = $PATH->rUpload.'/'.$folder.$dir;

                }

                $link = $path.$name;

                if (!empty($resize)) {
                    if (is_array($resize[0])) {
                        foreach ($resize as $k => $v) {

                            $WIDTH = $v['width'];
                            $HEIGHT = $v['height'];
                            unlink($path.$WIDTH.'x'.$HEIGHT.'-'.$name);

                        }
                    } else {

                        $WIDTH = $resize['width'];
                        $HEIGHT = $resize['height'];
                        unlink($path.$WIDTH.'x'.$HEIGHT.'-'.$name);

                    }
                }
                
                unlink($link);

            }

        }

        $JSON_ARRAY = json_encode($NEW_ARRAY);
        
        sqlModify($table, [$column => $JSON_ARRAY], 'id', $rowId);
        
    }

?>