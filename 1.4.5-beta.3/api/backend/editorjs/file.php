<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $RETURN = [ "success" => 0 ];

    if (isset($_FILES['file'])) {
        
        $file = $_FILES['file'];
        $folder = empty($_SERVER['HTTP_DIR']) ? '' : '/'.$_SERVER['HTTP_DIR'].'/';

        $tmp_file = $file['tmp_name'];
        $tmp_name = $file['name'];
        $fileSize = $file['size'];

        $mimeType = getMimeType($tmp_name);
        $extension = pathinfo($tmp_name, PATHINFO_EXTENSION);

        $code = code(10, 'all');
        $dir = $PATH->rUpload.$folder;

        $fileName = $code.'.'.$extension;

        $uploadFile = $dir.$fileName;

        if (move_uploaded_file($tmp_file, $uploadFile)) {

            $RETURN = [
                "success" => 1,
                "file" => [
                    "url" => $PATH->upload.$folder.$fileName,
                    "size" => $fileSize,
                    "name" => $tmp_name,
                    "extension" => $extension,
                    "mime-type" => $mimeType
                ]
            ];

        }

    }

    echo json_encode($RETURN);

?>