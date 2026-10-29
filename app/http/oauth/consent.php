<?php

use Wonder\Auth\AuthLog;
use Wonder\Auth\RememberMe;
use Wonder\Http\Csrf;
use Wonder\OAuth\AuthorizationCode;
use Wonder\OAuth\AuthorizationRequest;
use Wonder\OAuth\Config;
use Wonder\OAuth\Http;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\RedirectUri;
use Wonder\OAuth\ResourceRegistry;
use Wonder\OAuth\TokenIssuer;

Http::htmlPageHeaders();

try {
    $issuer = Config::issuer();
} catch (OAuthException $e) {
    $serverError = $e->status === 500;
    http_response_code($serverError ? 500 : 400);
    \Wonder\View\View::make($ROOT_APP.'/view/pages/oauth/error.php', [
        'titleKey' => $serverError ? 'oauth.error.server_title' : 'oauth.error.invalid_request_title',
        'messageKey' => $serverError ? 'oauth.error.server_message' : 'oauth.error.invalid_request_message',
    ])->render();
    exit;
}
$reqId = (string) ($_GET['req'] ?? $_POST['req'] ?? '');
$request = AuthorizationRequest::find($_SESSION, $reqId);
$view = static fn (string $page, array $data) => \Wonder\View\View::make($ROOT_APP.'/view/pages/oauth/'.$page.'.php', $data)->render();

if ($request === null) {
    http_response_code(400);
    $view('error', ['titleKey' => 'oauth.error.expired_title', 'messageKey' => 'oauth.error.expired_message']);
    exit;
}

$isUsable = static fn ($u): bool => $u !== null && ($u->exists ?? false) && ($u->active ?? false) === true && ($u->deleted ?? true) === false;

$userId = (int) ($_SESSION['user_id'] ?? 0);
$user = $userId > 0 ? infoUser($userId) : null;

if (!$isUsable($user)) {
    header('Location: '.$issuer.'/oauth/login?req='.$reqId, true, 303);
    exit;
}

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::verify()) {
        http_response_code(400);
        $view('error', ['titleKey' => 'oauth.error.expired_title', 'messageKey' => 'oauth.error.csrf_message']);
        exit;
    }

    $action = (string) ($_POST['action'] ?? '');

    if ($action === 'switch') {
        // Non logoutUser(): svuota $_SESSION e distrugge la sessione, perdendo la richiesta.
        // switchAccount la rimette in sessione dopo il logout.
        AuthorizationRequest::switchAccount($_SESSION, $reqId, static function () use ($user): void {
            AuthLog::write('logout', (int) $user->id, 'oauth', true);
            unset($_SESSION['user_id']);
            foreach ((array) $user->area as $area) {
                RememberMe::clear((string) $area);
            }
            if (session_status() === PHP_SESSION_ACTIVE) {
                session_regenerate_id(true);
            }
        });
        header('Location: '.$issuer.'/oauth/login?req='.$reqId, true, 303);
        exit;
    }

    // Gli scope restano quelli spuntati, mai oltre quelli chiesti dal client.
    $requested = (array) ($request['scopes'] ?? []);
    $approved = array_values(array_intersect($requested, array_filter((array) ($_POST['scopes'] ?? []), 'is_string')));

    if ($action !== 'approve' || ($requested !== [] && $approved === [])) {
        AuthorizationRequest::forget($_SESSION, $reqId);
        header('Location: '.AuthorizationRequest::redirectUrl($request['redirect_uri'], ['error' => 'access_denied', 'state' => $request['state'], 'iss' => $issuer]), true, 303);
        exit;
    }

    // Ricontrollo subito prima di emettere il codice: l'utente potrebbe essere stato
    // disattivato o cancellato dopo il login (o mentre la pagina era aperta).
    $fresh = infoUser($userId);
    if (!$isUsable($fresh)) {
        unset($_SESSION['user_id']);
        header('Location: '.$issuer.'/oauth/login?req='.$reqId, true, 303);
        exit;
    }

    AuthorizationRequest::forget($_SESSION, $reqId);

    $store = OAuth::store();
    $grantId = (new TokenIssuer($store))->grantFor((int) $fresh->id, $request['client_id'], $request['resource'], $approved);
    $code = (new AuthorizationCode($store))->issue((int) $fresh->id, $request, $grantId);

    header('Location: '.AuthorizationRequest::redirectUrl($request['redirect_uri'], ['code' => $code, 'state' => $request['state'], 'iss' => $issuer]), true, 303);
    exit;
}

$view('consent', [
    'request' => $request,
    'reqId' => $reqId,
    'user' => $user,
    'loopback' => RedirectUri::isLoopback($request['redirect_uri']),
    'redirectHost' => RedirectUri::host($request['redirect_uri']),
    'scopes' => array_intersect_key(ResourceRegistry::findByUrl($request['resource'])['scopes'] ?? [], array_flip((array) ($request['scopes'] ?? []))),
    'siteName' => (string) (($SOCIETY ?? null)->name ?? ''),
]);
