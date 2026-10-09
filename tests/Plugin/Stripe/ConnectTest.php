<?php
/** php tests/Plugin/Stripe/ConnectTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/FakeStripeHttp.php';

use Wonder\App\Credentials;
use Wonder\Plugin\Stripe\Connect;

const URL = 'https://negozio.example/api/gestionale/stripe/webhook/';

$http = FakeStripeHttp::install();

function api(bool $test): object
{
    $api = Credentials::apiDefaults();
    $api->stripe_test = $test;
    $api->stripe_private_key = 'sk_live_prova';
    $api->stripe_account_id = 'acct_live';
    $api->stripe_test_key = 'sk_test_prova';
    $api->stripe_test_account_id = 'acct_test';

    return $api;
}

check('Connect e i client del plugin restano sulla versione API basil', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['object' => 'list', 'url' => '/v1/webhook_endpoints', 'has_more' => false, 'data' => []]);
    $http->queue(200, ['id' => 'we_nuovo', 'object' => 'webhook_endpoint', 'url' => URL, 'secret' => 'whsec_prova']);
    (new Connect('sk_test_prova', 'acct_test'))->webhook(URL);
    $base = new class('sk_test_prova') extends \Wonder\Plugin\Stripe\Stripe {};

    return \Wonder\Plugin\Stripe\Stripe::API_VERSION === '2025-08-27.basil'
        && $base->getStripeVersion() === '2025-08-27.basil'
        && $http->header(0, 'Stripe-Version') === '2025-08-27.basil'
        && $http->header(1, 'Stripe-Version') === '2025-08-27.basil';
});

check('webhook: cancella l\'endpoint con lo stesso url, ne crea uno e dà il segreto', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['object' => 'list', 'url' => '/v1/webhook_endpoints', 'has_more' => false, 'data' => [
        ['id' => 'we_vecchio', 'object' => 'webhook_endpoint', 'url' => URL],
        ['id' => 'we_altro', 'object' => 'webhook_endpoint', 'url' => 'https://altro.example/hook'],
    ]]);
    $http->queue(200, ['id' => 'we_vecchio', 'object' => 'webhook_endpoint', 'deleted' => true]);
    $http->queue(200, ['id' => 'we_nuovo', 'object' => 'webhook_endpoint', 'url' => URL, 'secret' => 'whsec_nuovo']);

    $segreto = (new Connect('sk_test_prova', 'acct_test'))->webhook(URL);
    $creato = $http->requests[2]['params'];

    return $segreto === 'whsec_nuovo'
        && count($http->requests) === 3
        && [$http->requests[1]['method'], $http->path(1)] === ['DELETE', '/v1/webhook_endpoints/we_vecchio']
        && [$http->requests[2]['method'], $http->path(2)] === ['POST', '/v1/webhook_endpoints']
        && $creato['url'] === URL
        && array_values($creato['enabled_events']) === Connect::EVENTS
        && $http->header(0, 'Stripe-Account') === 'acct_test'
        && $http->header(2, 'Stripe-Account') === 'acct_test';
});

check('domain: un dominio già registrato non è un errore', function () use ($http) {
    $http->requests = [];
    $http->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'This domain is already registered.']]);
    (new Connect('sk_test_prova', 'acct_test'))->domain('negozio.example');

    return $http->path(0) === '/v1/payment_method_domains'
        && $http->requests[0]['params']['domain_name'] === 'negozio.example';
});

check('domain: gli altri errori passano', function () use ($http) {
    $http->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Invalid domain.']]);

    try {
        (new Connect('sk_test_prova', 'acct_test'))->domain('x');

        return false;
    } catch (\Stripe\Exception\InvalidRequestException) {
        return true;
    }
});

check('environment usa le chiavi dell\'ambiente scelto, non di quello attivo', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['object' => 'list', 'url' => '/v1/webhook_endpoints', 'has_more' => false, 'data' => []]);
    $http->queue(200, ['id' => 'we_1', 'object' => 'webhook_endpoint', 'url' => URL, 'secret' => 'whsec_live']);
    $http->queue(200, ['id' => 'pmd_1', 'object' => 'payment_method_domain']);

    // Il sito è in test, ma si collega la produzione.
    $segreto = Connect::environment('live', URL, 'negozio.example', api(true));

    return $segreto === 'whsec_live'
        && $http->header(0, 'Authorization') === 'Bearer sk_live_prova'
        && $http->header(0, 'Stripe-Account') === 'acct_live'
        && $http->header(2, 'Stripe-Account') === 'acct_live';
});

check('environment senza conto collegato si ferma prima di chiamare Stripe', function () use ($http) {
    $http->requests = [];
    $api = api(false);
    $api->stripe_test_account_id = '';

    try {
        Connect::environment('test', URL, 'negozio.example', $api);

        return false;
    } catch (RuntimeException) {
        return $http->requests === [];
    }
});

check('environment: un dominio rifiutato non perde il segreto', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['object' => 'list', 'url' => '/v1/webhook_endpoints', 'has_more' => false, 'data' => []]);
    $http->queue(200, ['id' => 'we_2', 'object' => 'webhook_endpoint', 'url' => URL, 'secret' => 'whsec_prova']);
    $http->queue(400, ['error' => ['type' => 'invalid_request_error', 'message' => 'Invalid domain.']]);

    return Connect::environment('live', URL, 'negozio.test', api(true)) === 'whsec_prova';
});

check('secretColumn per ambiente', fn () => Connect::secretColumn('test') === 'stripe_test_webhook_secret'
    && Connect::secretColumn('live') === 'stripe_webhook_secret');

summary();
