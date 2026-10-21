<?php

use Stripe\Exception\ApiErrorException;
use Wonder\App\Support\ApiRequest;
use Wonder\Http\Csrf;
use Wonder\Http\Route;
use Wonder\Plugin\Stripe\Connect;

// L'area API risponde JSON: qui sono pagine HTML.
header('Content-Type: text/html; charset=utf-8');

$method = strtoupper((string) ($_SERVER['REQUEST_METHOD'] ?? 'GET'));
$env = ApiRequest::string('account') === 'test' ? 'test' : 'live';

// GET non cambia niente: mostra la conferma che invia in POST con il token nel corpo.
if ($method === 'GET' || $method === 'HEAD') {
    $action = (string) parse_url((string) ($_SERVER['REQUEST_URI'] ?? ''), PHP_URL_PATH);

    echo '<!doctype html><html><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">'
        .'<title>'.e(__t('stripe.connect.title')).'</title></head><body>'
        .'<h1>'.e(__t('stripe.connect.title')).'</h1>'
        .'<p>'.e(__t('stripe.connect.confirm.'.$env)).'</p>'
        .'<form method="post" action="'.e($action).'">'
        .Csrf::field()->render()
        .'<input type="hidden" name="account" value="'.e($env === 'test' ? 'test' : 'production').'">'
        .'<button type="submit">'.e(__t('stripe.connect.submit')).'</button>'
        .'</form></body></html>';
    exit;
}

if ($method !== 'POST') {
    http_response_code(405);
    header('Allow: GET, POST');
    exit;
}

// Il token arriva solo dal corpo o dall'header, mai dalla query.
if (!Csrf::verify()) {
    http_response_code(403);
    echo e(__t('stripe.connect.invalid'));
    exit;
}

$url = Route::url('api.gestionale.stripe.webhook');

if ($url === '') {
    echo e(__t('stripe.connect.webhook_missing'));
    exit;
}

try {
    $secret = Connect::environment($env, $url, (string) parse_url($url, PHP_URL_HOST));
} catch (ApiErrorException|RuntimeException $e) {
    echo e(__t('stripe.connect.error', ['message' => $e->getMessage()]));
    exit;
}

sqlModify('security', [Connect::secretColumn($env) => $secret], 'id', 1);

header('Location: '.rtrim((string) $PATH->backend, '/').'/app/config/credentials/');
exit;
