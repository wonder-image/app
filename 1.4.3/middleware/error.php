<?php

    # Se ci sono degli errori mostro la pagina Errore
        if (isset($_GET['errCode']) && !empty($_GET['errCode'])) {

            require_once $ROOT_APP."/html/default/error.php";
            exit;
            
        }
        