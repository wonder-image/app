<?php

    function resizeImage($filePath, $maxWidth, $maxHeight, $newPath = null, $newName = null) {

        global $ALERT;

        if (!file_exists($filePath)) {

            $extension = pathinfo($filePath, PATHINFO_EXTENSION);

            if ($newName == null) {
                $name = basename($filePath, '.'.$extension);
            }else{
                $name = create_link($newName);
            }
            
            $extension = strtolower($extension);

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
            } else {
                $height = $maxHeight;
                $width = ($height / $originalHeight) * $originalWidth;
            }

            if ($extension == 'jpg' || $extension == 'jpeg') {

                $image = imagecreatefromjpeg($filePath);
                $imageResized = imagescale($image, $width, $height);
                imagejpeg($imageResized, $path);


            } elseif ($extension == 'png') {

                $image = imagecreatefrompng($filePath);
                $imageResized = imagescale($image, $width, $height);
                imagepng($imageResized, $path);
                
            }

        } else {

            $ALERT = 751;

        }
        
    }

?>