<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    use Wonder\App\Credentials;
    use Wonder\Plugin\Stripe\Accounts;

    if ($_GET['account'] == 'test') {
        
        $apiKey = Credentials::api()->stripe_test_key;
        $accountId = Credentials::api()->stripe_test_account_id;
        $columnKey = 'stripe_test_account_id';

    } else if ($_GET['account'] == 'production') {

        $apiKey = Credentials::api()->stripe_private_key;
        $accountId = Credentials::api()->stripe_account_id;
        $columnKey = 'stripe_account_id';

    }

    if ($accountId == '' || (isset($_GET['ca']) && $_GET['ca'] == 'true')) { 

        try {

            $account = Accounts::apiKey($apiKey);
            $accountId = Accounts::create()->id;

        } catch (\Stripe\Exception\ApiErrorException $e) {

            echo 'Errore Stripe: ' . $e->getMessage();
            exit;   

        }

        sqlModify('security', [ $columnKey => $accountId ], 'id', 1);

    }

    try {

        $onboarding = Accounts::onboarding($accountId);

        header("Location: $onboarding->url");
        
    } catch (\Stripe\Exception\ApiErrorException $e) {

        echo 'Errore Stripe: ' . $e->getMessage();
        exit; 

    }