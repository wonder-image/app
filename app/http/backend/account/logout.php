<?php

use Wonder\Backend\Support\ResourcePageController;
use Wonder\Http\Csrf;

    global $ALERT;

    // GET mostra solo la conferma: si esce in POST con il token CSRF.
    if (strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET')) === 'POST') {

        if (Csrf::verify()) {

            logoutUser('backend');

            header('Location: '.__r('backend.account.login'));
            exit;

        }

        $ALERT = ResourcePageController::CSRF_ALERT;

    }

    \Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/logout.php', [
        'TITLE' => __t('auth.logout.title'),
        'ALERT' => $ALERT ?? null,
    ])->render();
