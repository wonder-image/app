<?php

use Wonder\OAuth\Config;
use Wonder\OAuth\Http;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\TokenEndpoint;

try {
    Config::issuer();
    (new TokenEndpoint(OAuth::store()))->revoke(Http::body());
    Http::json([]);
} catch (OAuthException $e) {
    $e->send();
}
