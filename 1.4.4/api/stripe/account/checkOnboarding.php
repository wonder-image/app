<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    use Wonder\App\Credentials;
    use Wonder\Plugin\Stripe\Accounts;

    $apis = [ 
        'test' => [
            'api_key' => Credentials::api()->stripe_test_key,
            'id' => Credentials::api()->stripe_test_account_id,
            'column' => 'stripe_test_account_id'
        ], 
        'production' => [
            'api_key' => Credentials::api()->stripe_private_key,
            'id' => Credentials::api()->stripe_account_id,
            'column' => 'stripe_account_id'
        ]
    ];

    foreach ($apis as $key => $api) {
        
        $accountId = $api['id'];

        if (!empty($accountId)) {
                
            $account = Accounts::apiKey($api['api_key']);

            if (Accounts::get($accountId)->details_submitted == false) {
                sqlModify('security', [ $api['column'] => '' ], 'id', 1);
            }

        }

    }
    
    header("Location: $PATH->backend/config-app/credentials/");
        