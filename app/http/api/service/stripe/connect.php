<?php

use Stripe\Exception\ApiErrorException;
use Wonder\App\Support\ApiRequest;
use Wonder\Http\Csrf;
use Wonder\Http\Route;
use Wonder\Plugin\Stripe\Connect;

// Il link cambia le credenziali: senza il token della sessione non si tocca niente.
if (!Csrf::verify(ApiRequest::string('_csrf'))) {
    http_response_code(403);
    echo 'Errore Stripe: richiesta non valida, riapri la pagina delle credenziali.';
    exit;
}

$env = ApiRequest::string('account') === 'test' ? 'test' : 'live';
$url = Route::url('api.gestionale.stripe.webhook');

if ($url === '') {
    echo 'Errore Stripe: il webhook del gestionale non è installato.';
    exit;
}

try {
    $secret = Connect::environment($env, $url, (string) parse_url($url, PHP_URL_HOST));
} catch (ApiErrorException|RuntimeException $e) {
    echo 'Errore Stripe: '.htmlspecialchars($e->getMessage(), ENT_QUOTES, 'UTF-8');
    exit;
}

sqlModify('security', [Connect::secretColumn($env) => $secret], 'id', 1);

header('Location: '.rtrim((string) $PATH->backend, '/').'/app/config/credentials/');
exit;
