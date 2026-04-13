<?php

    $BACKEND = true;
    
    require_once "../../config.php";

    use Wonder\Api\{ Endpoint, Handler, Response };

    Handler::run($ROOT_API.'/backend/alert/', 'POST', 'api_internal_user', function (Endpoint $CALL) {

        $CALL->checkParameters([
            'code',
        ]);

        return Response::json(
            $CALL->response(alertTheme(
                $CALL->parameters['code'],
                $CALL->parameters['type'] ?? null,
                $CALL->parameters['title'] ?? null,
                $CALL->parameters['text'] ?? null
            ))
        );

    });
