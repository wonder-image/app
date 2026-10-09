<?php
/** php tests/App/CredentialsStripeTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Credentials;
use Wonder\App\Models\Config\Security;
use Wonder\App\Resources\Config\SecurityResource;

if (!function_exists('mailService')) {
    function mailService(): array
    {
        return ['phpmailer' => 'PHPMailer', 'brevo' => 'Brevo'];
    }
}

const NUOVE = ['stripe_public_key', 'stripe_test_public_key', 'stripe_webhook_secret', 'stripe_test_webhook_secret'];

// Le variabili d'ambiente della macchina non devono falsare le prove.
foreach (['STRIPE_TEST', 'STRIPE_TEST_KEY', 'STRIPE_PRIVATE_KEY', 'STRIPE_ACCOUNT_ID', 'STRIPE_TEST_ACCOUNT_ID',
    'STRIPE_PUBLIC_KEY', 'STRIPE_TEST_PUBLIC_KEY', 'STRIPE_WEBHOOK_SECRET', 'STRIPE_TEST_WEBHOOK_SECRET'] as $chiave) {
    unset($_ENV[$chiave]);
}

$credenziali = new class extends Credentials {
    public static function leggi(array $riga): object
    {
        $api = static::apiDefaults();
        static::stripe($api, $riga);

        return $api;
    }
};

$riga = [
    'stripe_test' => 'false',
    'stripe_private_key' => 'sk_live_prova',
    'stripe_test_key' => 'sk_test_prova',
    'stripe_account_id' => 'acct_live',
    'stripe_test_account_id' => 'acct_test',
    'stripe_public_key' => 'pk_live_prova',
    'stripe_test_public_key' => 'pk_test_prova',
    'stripe_webhook_secret' => 'whsec_live_prova',
    'stripe_test_webhook_secret' => 'whsec_test_prova',
];

check('i default hanno le sei chiavi nuove, vuote', function () {
    $api = Credentials::apiDefaults();

    foreach ([...NUOVE, 'stripe_publishable_key', 'stripe_webhook_key'] as $chiave) {
        if (($api->$chiave ?? null) !== '') {
            return false;
        }
    }

    return true;
});

check('produzione: chiave pubblica e segreto del webhook di produzione', function () use ($credenziali, $riga) {
    $api = $credenziali::leggi($riga);

    return $api->stripe_publishable_key === 'pk_live_prova'
        && $api->stripe_webhook_key === 'whsec_live_prova'
        && $api->stripe_api_key === 'sk_live_prova'
        && $api->stripe_id === 'acct_live';
});

check('test: chiave pubblica e segreto del webhook di test', function () use ($credenziali, $riga) {
    $api = $credenziali::leggi(['stripe_test' => 'true'] + $riga);

    return $api->stripe_publishable_key === 'pk_test_prova'
        && $api->stripe_webhook_key === 'whsec_test_prova'
        && $api->stripe_api_key === 'sk_test_prova'
        && $api->stripe_id === 'acct_test';
});

check('.env vince sulla riga anche per le chiavi nuove', function () use ($credenziali, $riga) {
    $_ENV['STRIPE_TEST_PUBLIC_KEY'] = 'pk_test_env';
    $_ENV['STRIPE_TEST'] = 'true';

    try {
        return $credenziali::leggi($riga)->stripe_publishable_key === 'pk_test_env';
    } finally {
        unset($_ENV['STRIPE_TEST_PUBLIC_KEY'], $_ENV['STRIPE_TEST']);
    }
});

check('la tabella security ha le quattro colonne', function () {
    $colonne = array_map(static fn ($column) => $column->name, Security::tableSchema());

    return array_diff(NUOVE, $colonne) === [];
});

check('il data schema ha le quattro chiavi', function () {
    $campi = array_map(static fn ($field) => $field->key, Security::dataSchema());

    return array_diff(NUOVE, $campi) === [];
});

check('la pagina Sicurezza ha i quattro campi', function () {
    $campi = array_map(static fn ($field) => $field->name, SecurityResource::formSchema());

    return array_diff(NUOVE, $campi) === [];
});

summary();
