<?php

use Wonder\Auth\AuthLog;
use Wonder\Auth\RememberMe;
use Wonder\Http\Csrf;
use Wonder\OAuth\AuthorizationRequest;
use Wonder\OAuth\Config;
use Wonder\OAuth\Http;

Http::htmlPageHeaders();

$reqId = (string) ($_GET['req'] ?? $_POST['req'] ?? '');
$request = AuthorizationRequest::find($_SESSION, $reqId);
$view = static fn (string $page, array $data) => \Wonder\View\View::make($ROOT_APP.'/view/pages/oauth/'.$page.'.php', $data)->render();

if ($request === null) {
    http_response_code(400);
    $view('error', ['titleKey' => 'oauth.error.expired_title', 'messageKey' => 'oauth.error.expired_message']);
    exit;
}

$alert = null;

if (($_SERVER['REQUEST_METHOD'] ?? '') === 'POST') {
    if (!Csrf::verify()) {
        $alert = 900;
    } else {
        $identifier = trim((string) ($_POST['username'] ?? ''));
        $candidate = infoUser($identifier, resolveUserLoginIdentifierKey($identifier));
        // Login unico: l'area di login è quella dell'utente (backend se ce l'ha, altrimenti la prima).
        $areas = ($candidate->exists ?? false) && is_array($candidate->area) ? $candidate->area : [];
        $area = in_array('backend', $areas, true) ? 'backend' : (string) ($areas[0] ?? 'frontend');

        // Se serve la verifica email, authenticateUser reindirizza al link inviato ed esce dal flusso OAuth.
        if (authenticateUserLogin($identifier, (string) ($_POST['password'] ?? ''), $area)) {
            // Il login OAuth non lascia un cookie remember-me sul sito.
            RememberMe::clear($area);
            AuthLog::write('oauth_login', (int) $_SESSION['user_id'], $area, true, ['client_id' => $request['client_id']]);
            header('Location: '.Config::issuer().'/oauth/consent?req='.$reqId, true, 303);
            exit;
        }
        $alert = (int) ($GLOBALS['ALERT'] ?? 905);
    }
}

$view('login', ['request' => $request, 'reqId' => $reqId, 'alert' => $alert, 'siteName' => (string) (($SOCIETY ?? null)->name ?? '')]);
