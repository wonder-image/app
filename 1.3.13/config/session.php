<?php

    # Imposto la sessione per la cache
        if (!isset($_SESSION['system_cache'])) { 

            $_SESSION['system_cache'] = [];

            $IP = new Wonder\Plugin\GeoPlugin\IPInfo();

            # Imposto il paese della sessione
                $_SESSION['system_cache']['country'] = $IP->Country();
        
            # Imposto la cache geo
                $_SESSION['system_cache']['geo'] = [];

        }