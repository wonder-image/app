<?php

use Stripe\Exception\ApiErrorException;
use Wonder\App\Support\ApiRequest;
use Wonder\Http\Route;
use Wonder\Plugin\Stripe\Connect;

$env = ApiRequest::string('account') === 'test' ? 'test' : 'live';
$url = Route::url('api.gestionale.stripe.webhook');

if ($url === '') {
    echo 'Errore Stripe: il webhook del gestionale non è installato.';
    exit;
}

try {
    $secret = Connect::environment($env, $url, (string) parse_url($url, PHP_URL_HOST));
} catch (ApiErrorException|RuntimeException $e) {
    echo 'Errore Stripe: '.$e->getMessage();
    exit;
}

sqlModify('security', [Connect::secretColumn($env) => $secret], 'id', 1);

header('Location: '.rtrim((string) $PATH->backend, '/').'/app/config/credentials/');
exit;
