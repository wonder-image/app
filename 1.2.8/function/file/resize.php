<?php

    function resizeImage($filePath, $maxWidth, $maxHeight, $newPath = null, $newName = null) {

        global $ALERT;

        if (!file_exists($filePath)) {

            $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

            if ($newName == null) {
                $newName = basename($filePath, '.'.$extension);
            } else{
                $newName = create_link($newName);
            }

            if ($newPath == null) {
                $newPath = "$newPath/$newName";
            } else {
                $newPath = pathinfo($filePath, PATHINFO_DIRNAME);
                $newPath = "/$newName";
            }

            $image = new ImageResize($filePath);
            $image->resizeToBestFit($maxWidth, $maxHeight);
            $image->save($newPath);

        } else {

            $ALERT = 751;

        }
        
    }

?>