<?php

    $BACKEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    use Wonder\App\{ Credentials, Path };
    use FattureInCloud\OAuth2\OAuth2AuthorizationCode\OAuth2AuthorizationCodeManager;
    use FattureInCloud\OAuth2\Scope;

    $oauth = new OAuth2AuthorizationCodeManager(
        Credentials::api()->fatture_in_cloud_client_id, 
        Credentials::api()->fatture_in_cloud_client_secret, 
        'https://api.wonderimage.it/v1.0/service/fatture-in-cloud/onboarding/'
    );

    if(!isset($_GET['code'])) {

        $url = $oauth->getAuthorizationUrl([ Scope::ENTITY_SUPPLIERS_READ ], "EXAMPLE_STATE");
        header('location: '.$url);

    } else {

        $code = $_GET['code'];
        $obj = $oauth->fetchToken($code);

        if(!isset($obj->error)) {
            $_SESSION['token'] = $obj->getAccessToken(); //saving the oAuth access token in a session variable
            $_SESSION['refresh'] = $obj->getRefreshToken();
        }

        echo 'Token saved correctly in the session variable';

    }