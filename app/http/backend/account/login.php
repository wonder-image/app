<?php

    if (isset($_POST['login'])) {

        if (authenticateUser('username', $_POST['username'], $_POST['password'], 'backend')) {

            if (!empty($PAGE->redirect)) {
                header("Location: $PAGE->redirect");
            } else {
                header("Location: ".__r('backend.home'));
            }

            exit();

        }

    }

    \Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/login.php', [
        'TITLE' => 'Login',
        'ALERT' => $ALERT ?? null,
        'fieldUsername' => $_POST['username'] ?? null,
    ])->render();
