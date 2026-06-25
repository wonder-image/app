<?php

    $FRONTEND = true;
    
    require_once __DIR__."/../../config.php";

    use Wonder\Api\{ Endpoint, Handler, Response };

    Handler::run($ROOT_API.'/frontend/alert/', 'POST', 'api_internal_user', function (Endpoint $CALL) {

        $CALL->checkParameters([
            'code',
        ]);

        return Response::json(
            $CALL->response(alertTheme($CALL->parameters['code']))
        );

    });
