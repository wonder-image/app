<?php

use Wonder\OAuth\Http;
use Wonder\OAuth\Metadata;
use Wonder\OAuth\OAuthException;
use Wonder\OAuth\ResourceRegistry;

try {
    $resource = ResourceRegistry::findByPath((string) (($ROUTE_META ?? [])['oauth_resource'] ?? ''));
    if ($resource === null) {
        throw new OAuthException('not_found', 'Unknown resource.', 404);
    }
    Http::json(Metadata::protectedResource($resource));
} catch (OAuthException $e) {
    $e->send();
}
