<?php

    # Loghi backend
        $DEFAULT->BeLogoBlack = \Wonder\App\RuntimeDefaults::backendLogoBlack($PATH);
        $DEFAULT->BeLogoWhite = \Wonder\App\RuntimeDefaults::backendLogoWhite($PATH);
        $DEFAULT->BeFavicon = \Wonder\App\RuntimeDefaults::backendFavicon();

    # Default
        $DEFAULT->image = \Wonder\App\RuntimeDefaults::defaultImage($PATH);
    
    # Dimensione icone per SEO
        $DEFAULT->appIcon = \Wonder\App\RuntimeDefaults::defaultAppIconSizes();
