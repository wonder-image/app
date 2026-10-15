<?php

use Wonder\OAuth\Http;
use Wonder\OAuth\Metadata;
use Wonder\OAuth\OAuthException;

try {
    Http::json(Metadata::authorizationServer());
} catch (OAuthException $e) {
    $e->send();
}
