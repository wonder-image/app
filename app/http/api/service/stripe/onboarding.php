<?php

use Wonder\App\Credentials;
use Wonder\App\Support\ApiRequest;
use Wonder\Plugin\Stripe\Accounts;

$account = ApiRequest::string('account') === 'test' ? 'test' : 'production';

if ($account === 'test') {
    $apiKey = Credentials::api()->stripe_test_key;
    $accountId = Credentials::api()->stripe_test_account_id;
    $columnKey = 'stripe_test_account_id';
} else {
    $apiKey = Credentials::api()->stripe_private_key;
    $accountId = Credentials::api()->stripe_account_id;
    $columnKey = 'stripe_account_id';
}

if ($accountId !== '' && ApiRequest::string('ca') !== 'true') {
    try {
        Accounts::get($accountId, $apiKey);
    } catch (\Stripe\Exception\InvalidRequestException | \Stripe\Exception\PermissionException) {
        // Conto dell'altro ambiente o cancellato: se ne crea uno nuovo.
        $accountId = '';
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo 'Errore Stripe: '.htmlspecialchars($e->getMessage(), ENT_QUOTES);
        exit;
    }
}

if ($accountId === '' || ApiRequest::string('ca') === 'true') {
    try {
        $accountId = Accounts::create([], $apiKey)->id;
    } catch (\Stripe\Exception\ApiErrorException $e) {
        echo 'Errore Stripe: '.htmlspecialchars($e->getMessage(), ENT_QUOTES);
        exit;
    }

    sqlModify('security', [$columnKey => $accountId], 'id', 1);
}

try {
    $onboarding = Accounts::onboarding($accountId, $apiKey, $account);
    header('Location: '.$onboarding->url);
    exit;
} catch (\Stripe\Exception\ApiErrorException $e) {
    echo 'Errore Stripe: '.htmlspecialchars($e->getMessage(), ENT_QUOTES);
    exit;
}
