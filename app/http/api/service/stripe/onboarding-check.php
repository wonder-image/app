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

    Accounts::apiKey($api['api_key']);

    if (Accounts::get($accountId)->details_submitted == false) {
        sqlModify('security', [$api['column'] => ''], 'id', 1);
    }
}

header('Location: '.rtrim((string) $PATH->backend, '/').'/app/config/credentials/');
exit;
