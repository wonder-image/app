<?php

    # Se ci sono degli errori mostro la pagina Errore
        if (isset($_GET['errCode']) && !empty($_GET['errCode'])) {

            $ERROR = (int) $_GET['errCode'];
            require_once $ROOT_APP."/view/error/http.php";
            exit;
            
        }
        
