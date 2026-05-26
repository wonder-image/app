<?php

use Wonder\App\PageSchema\AccountPageSchema;

    // authenticateUser() / verifyUser() popolano `$ALERT` come variabile
    // globale; senza `global` qui, il `$ALERT ?? null` qui sotto leggerebbe
    // la variabile LOCALE dello scope dell'handler (sempre null) e l'alert
    // a video non comparirebbe mai dopo un login fallito.
    global $ALERT;

    if (isset($_POST['login'])) {

        if (authenticateUserLogin($_POST['username'] ?? '', $_POST['password'] ?? '', 'backend')) {

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
        'fieldLogin' => $_POST['username'] ?? null,
        'FORM_SCHEMA' => AccountPageSchema::loginFormSchema(),
    ])->render();
