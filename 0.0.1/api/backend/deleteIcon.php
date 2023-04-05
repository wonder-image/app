<?php

    $PRIVATE = true;
        
    $BACKEND = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'].'/';

    include $ROOT.'app/wonder-image.php';

    if ($_POST['post']){

        $id = $_GET['id'];
        $table = $_GET['table'];
        $folder = $_GET['folder'];

        $sql = "SELECT * FROM $table WHERE id = '$id' LIMIT 1";
        $result = $mysqli->query($sql);
        $row = $result->fetch_assoc();

        $icon = $row['icon'];
        unlink("$pathUpload/$folder/$icon");

        $sql = "UPDATE $table SET icon = '' WHERE id = '$id'";
        $result = $mysqli->query($sql);

    }

?>