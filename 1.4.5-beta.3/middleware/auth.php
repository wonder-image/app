<?php

    if ($PRIVATE) {

        if (isset($BACKEND) && $BACKEND) {

            $USER = (empty($_POST)) ? authorizeUser('backend', $PERMIT, $_SESSION['user_id'] ?? null) : infoUser($_SESSION['user_id']);

        } elseif (isset($FRONTEND) && $FRONTEND) {

            $USER = authorizeUser('frontend', $PERMIT, $_SESSION['user_id'] ?? null);

        }

    }