<?php

use Wonder\OAuth\ClientRegistry;
use Wonder\OAuth\Config;
use Wonder\OAuth\Http;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;

try {
    Config::issuer();
    Http::json((new ClientRegistry(OAuth::store()))->register(Http::body(), Http::ip()), 201);
} catch (OAuthException $e) {
    $e->send();
}
