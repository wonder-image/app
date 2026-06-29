<?php

use Wonder\Api\{Endpoint, Handler, Response};

Handler::run('/api/frontend/alert/', 'POST', 'api_internal_user', function (Endpoint $call) {
    $call->checkParameters([
        'code',
    ]);

    return Response::json(
        $call->response(alertTheme($call->parameters['code']))
    );
});
