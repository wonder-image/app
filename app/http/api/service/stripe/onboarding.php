<?php

use Wonder\App\Credentials;
use Wonder\App\Support\ApiRequest;
use Wonder\Plugin\Stripe\Accounts;

$account = ApiRequest::string('account');

if ($account === 'test') {
    $apiKey = Credentials::api()->stripe_test_key;
    $accountId = Credentials::api()->stripe_test_account_id;
    $columnKey = 'stripe_test_account_id';
} else {
    $apiKey = Credentials::api()->stripe_private_key;
    $accountId = Credentials::api()->stripe_account_id;
    $columnKey = 'stripe_account_id';
}

if ($accountId === '' || ApiRequest::string('ca') === 'true') {
    try {
        Accounts::apiKey($apiKey);
        $accountId = Accounts::create()->id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo 'Errore Stripe: '.$e->getMessage();
        exit;
    }

    sqlModify('security', [$columnKey => $accountId], 'id', 1);
}

try {
    $onboarding = Accounts::onboarding($accountId);
    header('Location: '.$onboarding->url);
    exit;
} catch (\Stripe\Exception\ApiErrorException $e) {
    echo 'Errore Stripe: '.$e->getMessage();
    exit;
}
