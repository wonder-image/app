<?php

    if ($PRIVATE) {

        if (isset($BACKEND) && $BACKEND) {

            if (empty($_POST)) {
                $USER = authorizeUser('backend', $PERMIT, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);
            } else {
                $USER = infoUser($_SESSION['user_id']);
            }

        } elseif (isset($FRONTEND) && $FRONTEND) {

            $USER = authorizeUser('frontend', $PERMIT, isset($_SESSION['user_id']) ? $_SESSION['user_id'] : null);

        }

    }

?>