<?php
/** php tests/Plugin/Stripe/ClientConfigTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/FakeStripeHttp.php';

use Stripe\ApiRequestor;
use Stripe\HttpClient\CurlClient;
use Wonder\Plugin\Stripe\Connect;
use Wonder\Plugin\Stripe\PaymentIntent;
use Wonder\Plugin\Stripe\Stripe;

/**
 * CurlClient che non esce dalla macchina: tiene le opzioni cURL già
 * costruite (header compresi) e risponde con la coda.
 */
final class OfflineCurlClient extends CurlClient
{
    /** @var list<array> */
    public array $sent = [];

    /** @var list<array> */
    public array $responses = [];

    public function executeRequestWithRetries($opts, $absUrl, $maxNetworkRetries = null)
    {
        $this->sent[] = ['opts' => $opts, 'url' => $absUrl, 'retries' => $maxNetworkRetries];

        return [json_encode(array_shift($this->responses) ?? []), 200, []];
    }

    /** @return array<string, string> */
    public function headers(int $i): array
    {
        $named = [];

        foreach ($this->sent[$i]['opts'][\CURLOPT_HTTPHEADER] ?? [] as $line) {
            [$name, $value] = array_map('trim', explode(':', (string) $line, 2)) + [1 => ''];
            $named[strtolower($name)] = $value;
        }

        return $named;
    }
}

check('clientConfig: versione basil, chiave e due retry di rete', function () {
    $config = Stripe::clientConfig('sk_test_prova');

    return $config === ['api_key' => 'sk_test_prova', 'stripe_version' => '2025-08-27.basil', 'max_network_retries' => 2]
        && Stripe::clientConfig(null)['api_key'] === null;
});

check('httpClient: senza client installato prepara CurlClient con timeout 20 s e connessione 5 s', function () {
    ApiRequestor::setHttpClient(null);
    $client = Stripe::httpClient();
    $ok = $client instanceof CurlClient
        && $client->getTimeout() === 20
        && $client->getConnectTimeout() === 5
        && ApiRequestor::httpClient() === $client
        && \Stripe\Stripe::getMaxNetworkRetries() === 2;
    FakeStripeHttp::install();

    return $ok;
});

check('httpClient: un client finto installato resta quello', function () {
    $fake = FakeStripeHttp::install();

    return Stripe::httpClient() === $fake && ApiRequestor::httpClient() === $fake;
});

check('i client del plugin passano i retry alla richiesta', function () {
    $fake = FakeStripeHttp::install();
    $fake->queue(200, ['id' => 'pi_1', 'object' => 'payment_intent']);
    $fake->queue(200, ['object' => 'list', 'url' => '/v1/webhook_endpoints', 'has_more' => false, 'data' => []]);
    $fake->queue(200, ['id' => 'we_1', 'object' => 'webhook_endpoint', 'secret' => 'whsec_1']);
    $fake->queue(200, ['id' => 'cus_1', 'object' => 'customer']);

    (new PaymentIntent('sk_test_prova', 'acct_prova'))->create(['amount' => 100, 'currency' => 'eur'], 'pay_1');
    (new Connect('sk_test_prova', 'acct_prova'))->webhook('https://negozio.example/hook');
    $base = new class('sk_test_prova') extends Stripe {};
    $base->customers->create(['email' => 'cliente@example.test']);

    return array_column($fake->requests, 'retries') === [2, 2, 2, 2]
        && $fake->header(0, 'Idempotency-Key') === 'pay_1';
});

check('una POST senza chiave d\'idempotenza ne riceve una da CurlClient prima del ciclo dei retry', function () {
    $offline = new OfflineCurlClient();
    ApiRequestor::setHttpClient($offline);
    $offline->responses = [
        ['object' => 'list', 'url' => '/v1/webhook_endpoints', 'has_more' => false, 'data' => []],
        ['id' => 'we_2', 'object' => 'webhook_endpoint', 'secret' => 'whsec_2'],
        ['id' => 'pi_2', 'object' => 'payment_intent'],
    ];

    (new Connect('sk_test_prova', 'acct_prova'))->webhook('https://negozio.example/hook');
    (new PaymentIntent('sk_test_prova', 'acct_prova'))->create(['amount' => 100, 'currency' => 'eur'], 'pay_2');

    $creato = $offline->headers(1);
    $ok = $offline->getTimeout() === 20
        && $offline->getConnectTimeout() === 5
        && !isset($offline->headers(0)['idempotency-key'])
        && ($creato['idempotency-key'] ?? '') !== ''
        && $offline->headers(2)['idempotency-key'] === 'pay_2'
        && array_column($offline->sent, 'retries') === [2, 2, 2];
    FakeStripeHttp::install();

    return $ok;
});

summary();
