<?php

    namespace Wonder\Api;

    use Exception;

    class EndpointException extends Exception {

        public function __construct($message, $code = 0) {

            parent::__construct($message, $code);

        }

        public function getResponse() {

            return [
                "success" => false,
                "status" => $this->getCode() ?: 500,
                "response" => $this->getMessage()
            ];

        }

    }