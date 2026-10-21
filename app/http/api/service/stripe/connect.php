<?php

use Stripe\Exception\ApiErrorException;
use Wonder\App\Support\ApiRequest;
use Wonder\Http\Csrf;
use Wonder\Http\Route;
use Wonder\Plugin\Stripe\Connect;

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$env = ApiRequest::string('account') === 'test' ? 'test' : 'live';
$credentials = rtrim((string) ($PATH->backend ?? ''), '/').'/app/config/credentials/';

// Pagina di conferma con il layout backend.auth; $message è l'esito di un POST fallito.
$render = static function (?string $message = null) use ($ROOT_APP, $env, $credentials): never {
    \Wonder\View\View::make($ROOT_APP.'/view/pages/backend/service/stripe/connect.php', [
        'TITLE' => __t('stripe.connect.title'),
        'ACTION' => (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH),
        'ACCOUNT' => $env === 'test' ? 'test' : 'production',
        'CONFIRM' => __t('stripe.connect.confirm.'.$env),
        'MESSAGE' => $message,
        'CANCEL' => $credentials,
    ])->render();
    exit;
};

// HEAD: solo gli header, nessun corpo.
if ($method === 'HEAD') {
    exit;
}

// GET non cambia niente: mostra la conferma che invia in POST con il token nel corpo.
if ($method === 'GET') {
    $render();
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, HEAD, POST');
    exit;
}

// Il token arriva solo dal corpo o dall'header, mai dalla query.
if (!Csrf::verify()) {
    http_response_code(403);
    $render(__t('stripe.connect.invalid'));
}

$url = Route::url('api.gestionale.stripe.webhook');

if ($url === '') {
    $render(__t('stripe.connect.webhook_missing'));
}

try {
    $secret = Connect::environment($env, $url, (string) parse_url($url, PHP_URL_HOST));
} catch (ApiErrorException|RuntimeException $e) {
    $render(__t('stripe.connect.error', ['message' => $e->getMessage()]));
}

sqlModify('security', [Connect::secretColumn($env) => $secret], 'id', 1);

header('Location: '.$credentials);
exit;
