<?php
/** php tests/Plugin/Stripe/AccountsTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', dirname(__DIR__, 3));
define('ASSETS_VERSION', 'test');
define('APP_VERSION', 'test');

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/FakeStripeHttp.php';

use Wonder\App\Credentials;
use Wonder\Plugin\Stripe\Accounts;

$http = FakeStripeHttp::install();

// Il sito è in produzione: «Collega» di test deve comunque usare la chiave di test.
$api = Credentials::apiDefaults();
$api->stripe_test = false;
$api->stripe_api_key = 'sk_live_prova';
$api->stripe_id = 'acct_live';
(new ReflectionProperty(Credentials::class, 'API'))->setValue(null, $api);

check('create e get usano la chiave passata, non quella dell\'ambiente attivo', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['id' => 'acct_nuovo', 'object' => 'account']);
    $http->queue(200, ['id' => 'acct_nuovo', 'object' => 'account', 'details_submitted' => true]);

    $id = Accounts::create([], 'sk_test_prova')->id;
    Accounts::get($id, 'sk_test_prova');

    return $id === 'acct_nuovo'
        && $http->header(0, 'Authorization') === 'Bearer sk_test_prova'
        && $http->header(1, 'Authorization') === 'Bearer sk_test_prova'
        && $http->header(0, 'Stripe-Account') === null;
});

check('onboarding: chiave dell\'ambiente e link scaduto che riparte dallo stesso ambiente', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['object' => 'account_link', 'url' => 'https://connect.stripe.com/setup/prova']);
    $http->queue(200, ['object' => 'account_link', 'url' => 'https://connect.stripe.com/setup/prova']);

    Accounts::onboarding('acct_nuovo', 'sk_test_prova', 'test');
    Accounts::onboarding('acct_live', 'sk_live_prova');

    return $http->header(0, 'Authorization') === 'Bearer sk_test_prova'
        && str_ends_with((string) $http->requests[0]['params']['refresh_url'], '/service/stripe/onboarding/?account=test')
        && str_ends_with((string) $http->requests[1]['params']['refresh_url'], '/service/stripe/onboarding/?account=production');
});

check('senza chiave resta quella dell\'ambiente attivo', function () use ($http) {
    $http->requests = [];
    $http->queue(200, ['id' => 'acct_live', 'object' => 'account']);
    Accounts::get('acct_live');

    return $http->header(0, 'Authorization') === 'Bearer sk_live_prova';
});

check('le rotte di onboarding passano la chiave dell\'ambiente scelto', function () {
    $dir = __DIR__.'/../../../app/http/api/service/stripe/';
    $onboarding = (string) file_get_contents($dir.'onboarding.php');
    $check = (string) file_get_contents($dir.'onboarding-check.php');

    return !str_contains($onboarding.$check, 'Accounts::apiKey(')
        && str_contains($onboarding, 'Accounts::create([], $apiKey)')
        && str_contains($onboarding, 'Accounts::onboarding($accountId, $apiKey, $account)')
        && str_contains($check, "Accounts::get(\$accountId, \$api['api_key'])");
});

summary();
