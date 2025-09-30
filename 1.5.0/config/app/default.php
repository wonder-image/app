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