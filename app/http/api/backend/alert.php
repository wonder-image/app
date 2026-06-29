<?php

use Wonder\Api\{Endpoint, Handler, Response};

Handler::run('/api/backend/alert/', 'POST', 'api_internal_user', function (Endpoint $call) {
    $call->checkParameters([
        'code',
    ]);

    return Response::json(
        $call->response(alertTheme(
            $call->parameters['code'],
            $call->parameters['type'] ?? null,
            $call->parameters['title'] ?? null,
            $call->parameters['text'] ?? null
        ))
    );
});
