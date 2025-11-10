<?php

    # Informazioni della pagina
        $PAGE = infoPage();

    # Informazioni della società
        if (sqlTableExists('society')) { 

            $SOCIETY = infoSociety();
        
        } else {

            $SOCIETY->name = "Wonder Image";
            $SOCIETY->legal_name = "Wonder Image";
            $SOCIETY->email = "info@wonderimage.it";

        }

    # Immagini
        if (!defined('RESPONSIVE_IMAGE_SIZES')) {
            define('RESPONSIVE_IMAGE_SIZES', [ 240, 480, 620, 960, 1200, 1440, 1920, 2400 ]);
        }

        if (!defined('RESPONSIVE_IMAGE_WEBP')) {
            define('RESPONSIVE_IMAGE_WEBP', true);
        }