<?php

use Wonder\Api\{Endpoint, Handler, Response};

Handler::run('/api/backend/alert/', 'POST', 'api_internal_user', function (Endpoint $call) {
    $call->checkParameters([
        'code',
    ]);

    // alertToast() di wonder-image/lib invia alertType, alertTitle e alertText.
    $parameter = static fn (string $name): mixed => $call->parameters[$name]
        ?? $call->parameters['alert'.ucfirst($name)]
        ?? null;

    return Response::json(
        $call->response(alertTheme(
            $call->parameters['code'],
            $parameter('type'),
            $parameter('title'),
            $parameter('text')
        ))
    );
});
