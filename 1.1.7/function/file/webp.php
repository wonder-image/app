<?php

    function createWebp($filePath, $quality = 80, $newPath = null, $newName = null) {

        global $ALERT;

        if (function_exists('imagewebp')) {

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

                if ($estension == 'jpg' || $estension == 'jpeg') {

                    $image = imagecreatefromjpeg($filePath);
                    imagewebp($image, $path, $quality);

                }elseif ($estension == 'png') {

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

?>