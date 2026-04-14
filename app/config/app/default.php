<?php

    # Informazioni della pagina
        $PAGE = infoPage();

    # Immagini
        if (!defined('RESPONSIVE_IMAGE_SIZES')) {
            define('RESPONSIVE_IMAGE_SIZES', [ 240, 480, 620, 960, 1200, 1440, 1920, 2400 ]);
        }

        if (!defined('RESPONSIVE_IMAGE_WEBP')) {
            define('RESPONSIVE_IMAGE_WEBP', true);
        }