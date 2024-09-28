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

        return Wonder\App\Api::Call($endpoint, $values);
        
    }