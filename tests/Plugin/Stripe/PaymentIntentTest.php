<?php
/** php tests/Plugin/Stripe/PaymentIntentTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/FakeStripeHttp.php';

use Wonder\Plugin\Stripe\PaymentIntent;

$http = FakeStripeHttp::install();
$intenti = new PaymentIntent('sk_test_prova', 'acct_prova');

check('create: sul conto collegato, con la chiave d\'idempotenza', function () use ($http, $intenti) {
    $http->requests = [];
    $http->queue(200, ['id' => 'pi_1', 'object' => 'payment_intent', 'client_secret' => 'pi_1_secret_x', 'status' => 'requires_payment_method']);
    $pi = $intenti->create(['amount' => 1999, 'currency' => 'eur', 'metadata' => ['order_id' => '7']], 'pay_abc');

    return $pi->id === 'pi_1'
        && $http->requests[0]['method'] === 'POST'
        && $http->path(0) === '/v1/payment_intents'
        && $http->header(0, 'Stripe-Account') === 'acct_prova'
        && $http->header(0, 'Idempotency-Key') === 'pay_abc'
        && $http->header(0, 'Authorization') === 'Bearer sk_test_prova'
        && (int) $http->requests[0]['params']['amount'] === 1999;
});

check('get, update e cancel passano dal conto collegato', function () use ($http, $intenti) {
    $http->requests = [];
    $http->queue(200, ['id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'requires_payment_method']);
    $http->queue(200, ['id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'requires_payment_method']);
    $http->queue(200, ['id' => 'pi_1', 'object' => 'payment_intent', 'status' => 'canceled']);
    $intenti->get('pi_1');
    $intenti->update('pi_1', ['amount' => 2500]);
    $cancellato = $intenti->cancel('pi_1');

    return $cancellato->status === 'canceled'
        && [$http->requests[0]['method'], $http->path(0)] === ['GET', '/v1/payment_intents/pi_1']
        && [$http->requests[1]['method'], $http->path(1)] === ['POST', '/v1/payment_intents/pi_1']
        && [$http->requests[2]['method'], $http->path(2)] === ['POST', '/v1/payment_intents/pi_1/cancel']
        && $http->header(0, 'Stripe-Account') === 'acct_prova'
        && $http->header(1, 'Stripe-Account') === 'acct_prova'
        && $http->header(2, 'Stripe-Account') === 'acct_prova';
});

check('createCustomer: sul conto collegato, con la chiave d\'idempotenza', function () use ($http, $intenti) {
    $http->requests = [];
    $http->queue(200, ['id' => 'cus_1', 'object' => 'customer']);
    $cliente = $intenti->createCustomer(['email' => 'cliente@example.com'], 'cus_42');

    return $cliente->id === 'cus_1'
        && $http->path(0) === '/v1/customers'
        && $http->header(0, 'Stripe-Account') === 'acct_prova'
        && $http->header(0, 'Idempotency-Key') === 'cus_42';
});

check('refundsOf: i rimborsi della carica, con importo e stato', function () use ($http, $intenti) {
    $http->requests = [];
    $http->queue(200, ['object' => 'list', 'url' => '/v1/refunds', 'has_more' => false, 'data' => [
        ['id' => 're_1', 'object' => 'refund', 'amount' => 500, 'status' => 'succeeded'],
        ['id' => 're_2', 'object' => 'refund', 'amount' => 300, 'status' => 'failed'],
    ]]);
    $rimborsi = $intenti->refundsOf('ch_1');

    return $rimborsi === [
            ['id' => 're_1', 'amount' => 500, 'status' => 'succeeded'],
            ['id' => 're_2', 'amount' => 300, 'status' => 'failed'],
        ]
        && $http->requests[0]['params']['charge'] === 'ch_1'
        && $http->header(0, 'Stripe-Account') === 'acct_prova';
});

summary();
