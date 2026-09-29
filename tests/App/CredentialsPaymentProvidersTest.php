<?php
/** php tests/App/CredentialsPaymentProvidersTest.php */
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

$credentialKeys = [
    'paypal_live', 'paypal_client_id', 'paypal_client_secret',
    'nexi_prod', 'nexi_api_key', 'nexi_alias', 'nexi_mac_key',
];

check('default PayPal e Nexi disponibili in Credentials', function () use ($credentialKeys) {
    $defaults = Credentials::apiDefaults();

    foreach ($credentialKeys as $key) {
        if (!property_exists($defaults, $key)) {
            return false;
        }
    }

    return $defaults->paypal_live === false && $defaults->nexi_prod === false;
});

check('tabella security contiene tutte le credenziali', function () use ($credentialKeys) {
    $columns = array_map(static fn ($column) => $column->name, Security::tableSchema());

    return array_diff($credentialKeys, $columns) === [];
});

check('data schema contiene tutte le credenziali', function () use ($credentialKeys) {
    $fields = array_map(static fn ($field) => $field->key, Security::dataSchema());

    return array_diff($credentialKeys, $fields) === [];
});

check('pagina credenziali contiene i campi PayPal e Nexi', function () use ($credentialKeys) {
    $fields = array_map(static fn ($field) => $field->name, SecurityResource::formSchema());

    return array_diff($credentialKeys, $fields) === [];
});

summary();
