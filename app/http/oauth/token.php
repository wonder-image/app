<?php

use Wonder\OAuth\Config;
use Wonder\OAuth\Http;
use Wonder\OAuth\OAuth;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\TokenEndpoint;

try {
    Config::issuer();
    Http::json((new TokenEndpoint(OAuth::store()))->token(Http::body()));
} catch (OAuthException $e) {
    $e->send();
} catch (\Throwable $e) {
    error_log('[oauth] '.basename(__FILE__, '.php').': '.get_class($e).': '.$e->getMessage());
    Http::json(['error' => 'server_error'], 500);
}
