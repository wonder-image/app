<?php

use Wonder\OAuth\AuthorizationRequest;
use Wonder\OAuth\ClientRegistry;
use Wonder\OAuth\Config;
use Wonder\OAuth\Http;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;

Http::htmlPageHeaders();

try {
    $issuer = Config::issuer();
    $request = AuthorizationRequest::validate($_GET, new ClientRegistry(OAuth::store()));
} catch (OAuthException $e) {
    if ($e->redirectUri !== null && isset($issuer)) {
        // RFC 9207: l'issuer accompagna anche gli errori.
        header('Location: '.AuthorizationRequest::redirectUrl($e->redirectUri, [
            'error' => $e->error, 'error_description' => $e->description, 'state' => $e->state, 'iss' => $issuer,
        ]), true, 302);
        exit;
    }
    http_response_code($e->status === 500 ? 500 : 400);
    \Wonder\View\View::make($ROOT_APP.'/view/pages/oauth/error.php', [
        'titleKey' => 'oauth.error.invalid_request_title', 'messageKey' => 'oauth.error.invalid_request_message',
    ])->render();
    exit;
}

$reqId = AuthorizationRequest::store($request, $_SESSION);
$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = $userId > 0 ? infoUser($userId) : null;
$target = ($user !== null && ($user->exists ?? false) && $user->active === true && $user->deleted === false) ? 'consent' : 'login';

header('Location: '.$issuer.'/oauth/'.$target.'?req='.$reqId, true, 302);
exit;
