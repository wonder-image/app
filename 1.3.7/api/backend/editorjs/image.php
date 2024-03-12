<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $RETURN = [ "success" => 0 ];

    if (isset($_FILES['image'])) {
        
        $image = $_FILES['image'];
        $folder = empty($_SERVER['HTTP_DIR']) ? '' : '/'.$_SERVER['HTTP_DIR'].'/';

        $tmp_name = $image['name'];
        $fileSize = $image['size'];
        $tmp_file = $image['tmp_name'];

        $mimeType = getMimeType($tmp_name);
        $extension = pathinfo($tmp_name, PATHINFO_EXTENSION);

        $code = code(10, 'all');
        $dir = $PATH->rUpload.$folder.$code;

        mkdir($dir);

        $uploadImage = $dir.'/original.'.$extension;

        if (move_uploaded_file($tmp_file, $uploadImage)) {

            resizeImage($uploadImage, 600, 600, $dir, 'small');
            resizeImage($uploadImage, 1200, 1200, $dir, 'medium');
            resizeImage($uploadImage, 1920, 1920, $dir, 'large');

            $RETURN = [
                "success" => 1,
                "file" => [
                    "url" => $PATH->upload.$folder.$code.'/original.'.$extension,
                    "original" => $PATH->upload.$folder.$code.'/original.'.$extension,
                    "large" => $PATH->upload.$folder.$code.'/large.'.$extension,
                    "medium" => $PATH->upload.$folder.$code.'/medium.'.$extension,
                    "small" => $PATH->upload.$folder.$code.'/small.'.$extension,
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