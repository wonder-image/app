<?php
    
    if (sqlTableExists('seo')) { $SEO = infoSeo(); }

    // Imposto un codice univoco al visitatore

        if (isset($_COOKIE['visitor_id'])) {

            $VISITOR_ID = $_COOKIE['visitor_id'];

        } else {
            
            $VISITOR_ID = strtolower(code(25, 'letters'));

            setcookie(
                "visitor_id",
                $VISITOR_ID,
                time() + (10 * 365 * 24 * 60 * 60)
            );

        }

    // 

    // Controllo se l'utente è registrato

        if (isset($_SESSION['user_id'])) {

            $USER_ID = $_SESSION['user_id'];
            $REGISTERED_USER = in_array("frontend", infoUser($_SESSION['user_id'])->area) ? "true" : "false";

        } else {

            $USER_ID = "";
            $REGISTERED_USER = "false";

        }

    // 

    $SESSION_ID = session_id();

    $ACTIVE_STATISTICS = isset($ACTIVE_STATISTICS) ? $ACTIVE_STATISTICS : true;

    include $ROOT.'/custom/utility/frontend/set-up.php';

?>