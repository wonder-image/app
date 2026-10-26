<?php

use Wonder\App\Support\ApiRequest;
use Wonder\Http\{ Csrf, SameOrigin };

// Token CSRF della sessione corrente, per i form AJAX rimasti aperti oltre la
// sessione: solo POST dallo stesso sito, senza guard e senza verify (la pagina
// non ha più un token valido da mandare). Non dà accesso ad altro.
header('Cache-Control: no-store');

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

if (!SameOrigin::check()) {
    ApiRequest::error('Richiesta non consentita.', 403);
}

if (session_status() !== PHP_SESSION_ACTIVE) {
    ApiRequest::error('Sessione non disponibile.', 403);
}

ApiRequest::success('ok', [
    'token' => Csrf::token(),
]);
