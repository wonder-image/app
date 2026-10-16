<?php

use Wonder\App\LegacyGlobals;
use Wonder\App\Resources\OAuth\ConnectionsResource;
use Wonder\App\Support\ApiRequest;

if (!ApiRequest::isPost()) {
    ApiRequest::error('Metodo non consentito.', 405);
}

$user = LegacyGlobals::get('USER');

if (!ConnectionsResource::revoke(ApiRequest::int('id'), is_object($user) ? $user : null)) {
    ApiRequest::error(__t('oauth.connections.revoke_error'), 403);
}

ApiRequest::success(__t('oauth.connections.revoked_message'));
