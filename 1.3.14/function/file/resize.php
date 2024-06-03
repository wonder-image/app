<?php

    function resizeImage($filePath, $maxWidth, $maxHeight, $newPath = null, $newName = null) {

        global $ALERT;

        if (file_exists($filePath)) {

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($newName == null) {
                $newName = basename($filePath, '.'.$extension).".$extension";
            } else{
                $newName = create_link($newName).".$extension";
            }
            
            $newPath = ($newPath == null) ? $newName : $newPath."/$newName";

            $image = new \Gumlet\ImageResize($filePath);
            $image->resizeToBestFit($maxWidth, $maxHeight);
            $image->save($newPath);

        } else {

            $ALERT = 751;

        }
        
    }