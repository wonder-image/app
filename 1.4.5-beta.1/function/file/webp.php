<?php

    function createWebp($filePath, $quality = 80, $newPath = null, $newName = null) {

        global $ALERT;

        if (function_exists('imagewebp')) {

            if (!file_exists($filePath)) {

                $extension = pathinfo($filePath, PATHINFO_EXTENSION);

                if ($newName == null) {
                    $name = basename($filePath, '.'.$extension);
                } else{
                    $name = create_link($newName);
                }
                
                $extension = strtolower($extension);

                if ($newPath == null) {
                    $path = "$newPath/$name.webp";
                } else {
                    $path = pathinfo($filePath, PATHINFO_DIRNAME);
                    $path = "/$name.webp";
                }

                if ($extension == 'jpg' || $extension == 'jpeg') {

                    $image = imagecreatefromjpeg($filePath);
                    imagewebp($image, $path, $quality);

                } elseif ($extension == 'png') {

                    $image = imagecreatefrompng($filePath);
                    imagewebp($image, $path, $quality);
                    
                }

            } else {

                $ALERT = 751;

            }
            
        } else {

            $ALERT = 701;

        }
        
    }