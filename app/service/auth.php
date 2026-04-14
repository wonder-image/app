<?php

    if (isset($PRIVATE) && $PRIVATE) {

        if (isset($BACKEND) && $BACKEND) {

            $USER = authorizeUser('backend', $PERMIT, $_SESSION['user_id'] ?? null);

        } elseif (isset($FRONTEND) && $FRONTEND) {

            $USER = authorizeUser('frontend', $PERMIT, $_SESSION['user_id'] ?? null);

        }

    }
