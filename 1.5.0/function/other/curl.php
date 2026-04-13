<?php

    function curl( string $url, ?string $action = null, array $values = [], ?string $username = null, ?string $password = null) {

        $CALL = new Wonder\Api\Call($url, $values);

        $CALL->method($action);

        if ($username != null && $password != null) {
            $CALL->authBasic($username, $password);
        }

        return $CALL->result();

    }

    function wiApi( string $endpoint, array $values = []) {

        return Wonder\App\Api::Call($endpoint, $values);
        
    }

    function wiApiJson( string $endpoint, array $values = []): ?array {

        $response = wiApi($endpoint, $values);

        if (!is_string($response) || trim($response) === '') {
            return null;
        }

        $payload = json_decode($response, true);

        return is_array($payload) ? $payload : null;

    }

    function curlJson(
        string $url,
        ?string $action = null,
        array $values = [],
        ?string $bearer = null,
        ?string $username = null,
        ?string $password = null
    ): ?array {

        $CALL = new Wonder\Api\Call($url, $values);

        $CALL->method($action);
        $CALL->contentType('application/json');

        if ($bearer !== null && trim($bearer) !== '') {
            $CALL->authBearer($bearer);
        }

        if ($username != null && $password != null) {
            $CALL->authBasic($username, $password);
        }

        $response = $CALL->result();

        if (!is_string($response) || trim($response) === '') {
            return null;
        }

        $payload = json_decode($response, true);

        return is_array($payload) ? $payload : null;

    }
