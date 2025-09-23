<?php

    $BACKEND = true;
    
    require_once "../../config.php";

    use Wonder\Api\{ Endpoint, EndpointException };

    try {

        $CALL = (new Endpoint($ROOT_API."/backend/alert/", "POST", "api_internal_user"))
            ->checkParameters([
                'code'
            ]);

        $RESPONSE = $CALL->response(alertTheme(
            $CALL->parameters['code'],
            $CALL->parameters['type'] ?? null,
            $CALL->parameters['title'] ?? null,
            $CALL->parameters['text'] ?? null
        ));

    } catch (EndpointException $e) {

        http_response_code($e->getCode() ?: 400);
        $RESPONSE = $e->getResponse();
        
    } catch (Exception $e) {

        http_response_code($e->getCode(),);

        $RESPONSE = [
            "success"  => false,
            "status"   => $e->getCode() ?: 500,
            "response" => $e->getMessage()
        ];

    }

    echo json_encode($RESPONSE, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    exit();