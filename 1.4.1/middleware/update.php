<?php

    # Controllo se bisogna aggiornare l'app
    # O se è già stato effettuato il primo setup
        if ((isset($_GET['updateApp']) && !empty($_GET['updateApp'])) || !sqlTableExists('security')) {

            require_once $ROOT_APP."/html/default/update.php";
            exit;
            
        }
        