<?php

    function curl( string $url, string $action = null, array $values = [], string $username = null, string $password = null) {

        $CALL = new Wonder\Api\Call($url, $values);

        $CALL->method($action);

        if ($username != null && $password != null) {
            $CALL->authBasic($username, $password);
        }

        return $CALL->result();

    }

    function wiApi( string $endpoint, array $values = []) {

        global $API;
        
        $url = $API->endpoint.$endpoint;

        $CALL = new Wonder\Api\Call($url, $values);
        $CALL->method('POST');
        $CALL->contentType('application/json');
        $CALL->authBearer($API->key);

        return $CALL->result();

    }