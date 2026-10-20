<?php

    /**
     * Ridimensiona per stare dentro $maxWidth x $maxHeight senza ingrandire
     * (Intervention Image, driver GD). Restituisce false se il file manca o
     * non è un'immagine leggibile.
     */
    function resizeImage($filePath, $maxWidth, $maxHeight, $newPath = null, $newName = null): bool {

        global $ALERT;

        if (!file_exists($filePath)) {
            $ALERT = 751;
            return false;
        }

        $extension = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));

        if ($newName == null) {
            $newName = basename($filePath, '.'.$extension).".$extension";
        } else {
            $newName = create_link($newName).".$extension";
        }

        $newPath = ($newPath == null) ? $newName : $newPath."/$newName";

        try {
            (new \Intervention\Image\ImageManager(new \Intervention\Image\Drivers\Gd\Driver()))
                ->read($filePath)
                ->scaleDown((int) $maxWidth, (int) $maxHeight)
                ->save($newPath);
        } catch (\Throwable) {
            return false;
        }

        return true;

    }

    function imageResize($imagePath, array $sizes = RESPONSIVE_IMAGE_SIZES, bool $webp = true)
    {

        global $ALERT;

        try {

            $image = Wonder\Plugin\Custom\Image\ResponsiveImage::path($imagePath);

            $image->sizes = $sizes;
            $image->webp = $webp;

            $image->generate();

        } catch (\Throwable $th) {

            $ALERT = 920;

        }

    }