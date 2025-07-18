<?php

    # Informazioni della pagina
        $PAGE = infoPage();

    # Informazioni della società
        if (sqlTableExists('society')) { $SOCIETY = infoSociety(); }