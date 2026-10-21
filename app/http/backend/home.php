<?php

$TITLE = 'Home';

// Il POST non scrive nulla: senza token valido mostra solo l'alert.
if (($_SERVER['REQUEST_METHOD'] ?? 'GET') === 'POST' && !\Wonder\Http\Csrf::verify()) {
    global $ALERT;
    $ALERT = \Wonder\Backend\Support\ResourcePageController::CSRF_ALERT;
}
\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/home.php', [
    'TITLE' => $TITLE,
    'NAV_BACKEND' => is_array($NAV_BACKEND ?? null) ? $NAV_BACKEND : [],
    'USER' => is_object($USER ?? null) ? $USER : (object) [ 'authority' => [] ],
])->render();
