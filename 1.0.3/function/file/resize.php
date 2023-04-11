<?php

    function resizeImage($filePath, $maxWidth, $maxHeight, $newPath = null, $newName = null) {

        global $ALERT;

        if (!file_exists($filePath)) {

            $estension = pathinfo($filePath, PATHINFO_EXTENSION);

            if ($newName == null) {
                $name = basename($filePath, '.'.$estension);
            }else{
                $name = create_link($newName);
            }
            
            $estension = strtolower($estension);

            if ($newPath == null) {
                $path = "$newPath/$name.webp";
            } else {
                $path = pathinfo($filePath, PATHINFO_DIRNAME);
                $path = "/$name.webp";
            }

            list($originalWidth, $originalHeight) = getimagesize($filePath);
            if ($originalWidth >= $originalHeight) {
                $width = $maxWidth;
                $height = ($width / $originalWidth) * $originalHeight;
            }else{
                $height = $maxHeight;
                $width = ($height / $originalHeight) * $originalWidth;
            }

            if ($estension == 'jpg' || $estension == 'jpeg') {

                $image = imagecreatefromjpeg($filePath);
                $imageResized = imagescale($image, $width, $height);
                imagejpeg($imageResized, $path);


            }elseif ($estension == 'png') {

                $image = imagecreatefrompng($filePath);
                $imageResized = imagescale($image, $width, $height);
                imagepng($imageResized, $path);
                
            }

        } else {

            $ALERT = 751;

        }
        
    }

?>