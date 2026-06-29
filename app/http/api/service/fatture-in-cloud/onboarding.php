<?php

use FattureInCloud\OAuth2\OAuth2AuthorizationCode\OAuth2AuthorizationCodeManager;
use FattureInCloud\OAuth2\Scope;
use Wonder\App\Credentials;
use Wonder\App\Path;

$oauth = new OAuth2AuthorizationCodeManager(
    Credentials::api()->fatture_in_cloud_client_id,
    Credentials::api()->fatture_in_cloud_client_secret,
    rtrim((new Path)->appApi, '/').'/service/fatture-in-cloud/onboarding/'
);

if (!isset($_GET['code'])) {
    $url = $oauth->getAuthorizationUrl([Scope::ENTITY_SUPPLIERS_READ], 'EXAMPLE_STATE');
    header('Location: '.$url);
    exit;
}

$code = $_GET['code'];
$obj = $oauth->fetchToken($code);

if (!isset($obj->error)) {
    $_SESSION['token'] = $obj->getAccessToken();
    $_SESSION['refresh'] = $obj->getRefreshToken();
}

echo 'Token saved correctly in the session variable';
