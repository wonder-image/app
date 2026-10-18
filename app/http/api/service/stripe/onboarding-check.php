<?php

use Wonder\App\Credentials;
use Wonder\Plugin\Stripe\Accounts;

$apis = [
    'test' => [
        'api_key' => Credentials::api()->stripe_test_key,
        'id' => Credentials::api()->stripe_test_account_id,
        'column' => 'stripe_test_account_id',
    ],
    'production' => [
        'api_key' => Credentials::api()->stripe_private_key,
        'id' => Credentials::api()->stripe_account_id,
        'column' => 'stripe_account_id',
    ],
];

foreach ($apis as $api) {
    $accountId = $api['id'];

    if (empty($accountId)) {
        continue;
    }

    try {
        $submitted = (bool) Accounts::get($accountId, $api['api_key'])->details_submitted;
    } catch (\Stripe\Exception\ApiErrorException) {
        // Non si legge con la chiave di questo ambiente: lo sistema il prossimo «Collega».
        continue;
    }

    if (!$submitted) {
        sqlModify('security', [$api['column'] => ''], 'id', 1);
    }
}

header('Location: '.rtrim((string) $PATH->backend, '/').'/app/config/credentials/');
exit;
