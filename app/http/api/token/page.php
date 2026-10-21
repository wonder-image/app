<?php

use Wonder\Api\Token\{ ApiTokens, TokenConfig };
use Wonder\App\Support\ApiRequest;
use Wonder\Http\{ Csrf, SameOrigin };

// Rinnovo del token di pagina: nessun guard, ma solo dallo stesso sito, con
// sessione attiva e token CSRF (`X-WI-CSRF` o `_csrf`). Resta legato a `sid`.
header('Cache-Control: no-store');

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

if (!SameOrigin::check()) {
    ApiRequest::error('Richiesta non consentita.', 403);
}

if (ApiTokens::currentSessionId() === null) {
    ApiRequest::error('Sessione non disponibile.', 403);
}

if (!Csrf::verify()) {
    ApiRequest::error('Richiesta non consentita.', 403);
}

$token = ApiTokens::pageToken();

if ($token === '') {
    ApiRequest::error('Token non disponibile.', 503);
}

ApiRequest::success('ok', [
    'token' => $token,
    'expires_in' => TokenConfig::pageTtl(),
]);
