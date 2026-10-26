<?php

    # Imposto la sessione per la cache
        if (!isset($_SESSION['system_cache'])) { 

            $_SESSION['system_cache'] = [];

            # Il paese si calcola al primo uso: Wonder\Plugin\GeoPlugin\Geo::country()

            # Imposto la cache geo
                $_SESSION['system_cache']['geo'] = [];

        }


    # Impersonificazione scaduta: torna all'attore prima di risolvere l'utente
        \Wonder\Auth\Impersonation::enforceExpiry();

    # Imposto la sessione con utente non definito se non è impostato
        if (!isset($_SESSION['user_id'])) { $_SESSION['user_id'] = null; }