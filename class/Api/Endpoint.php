<?php

    namespace Wonder\Api;

    use Exception;

    class Endpoint {

        private $version = "v1.0";
        private $endpoint = "";
        public $api_key = "";
        public $ip = "";
        public $parameters = [];

        function __construct($endpoint = "") {

            $this->endpoint = $endpoint;

        }

        function checkIp() {

            if (isset($_SERVER['REMOTE_ADDR']) && $_SERVER['REMOTE_ADDR'] != "") {
                $this->ip = $_SERVER['REMOTE_ADDR'];
            } else {
                throw new Exception('Indirizzo IP non trovato! ', 400); 
            }

        }

        function checkEndpoint() {

            $parsedURI = parse_url($_SERVER["REQUEST_URI"]);
            $endpointName = str_replace('/'.$this->version, "", $parsedURI["path"]);
            
            if ($endpointName != $this->endpoint) { 
                throw new Exception('Endpoint non trovato! ', 400); 
            }

        }

        function checkContentType() {

            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/json') { 
                throw new Exception('CONTENT_TYPE non valido!', 405); 
            }

        }

        function checkRequestMethod() {
            
            if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != 'POST') { 
                throw new Exception('REQUEST_METHOD non valido!', 405); 
            }

        }

        function basicCheck() {

            $this->checkIp();
            $this->checkEndpoint();
            $this->checkContentType();
            $this->checkRequestMethod();

        }

        function advancedCheck() {

            $this->basicCheck();
            $this->checkApiKey();
            $this->verifyApiKey();

        }

        function checkApiKey() {

            if (!isset($_SERVER['HTTP_AUTHORIZATION']) || $_SERVER['HTTP_AUTHORIZATION'] == '') {

                throw new Exception('API key mancante!', 401); 

            } else {

                $this->api_key = str_replace("Bearer ", "", $_SERVER['HTTP_AUTHORIZATION']);

            }

        }

        function verifyApiKey() {

            $USER = sqlSelect('user', [
                'api_key' => $this->api_key,
                'active' => 'true',
                'deleted' => 'false',
            ], 1);

            if (!$USER->exists) {

                throw new Exception('API key non valida', 401); 

            } else if ($USER->row['ip'] != $this->ip) {

                throw new Exception('API key non valida per questo IP', 401); 

            }

        }

        function checkParameters(array|string $parameters) {

            $receivedParameters = file_get_contents("php://input");
            $this->parameters = (!isset($receivedParameters) || empty($receivedParameters)) ? [] : json_decode($receivedParameters, true);

            if (is_array($parameters)) {
                
                foreach ($parameters as $parameter) {
                    if (!array_key_exists($parameter, $this->parameters)) {
                        throw new Exception('Parametro '.$parameter.' mancante!', 401); 
                    }
                }

            } else {

                if (!in_array($parameters, $this->parameters)) {
                    throw new Exception('Parametro '.$parameters.' mancante!', 401); 
                }

            }

        }

        function response(array|string $response = [], int $status = 200) {

            $success = ($status == 200) ? true : false;

            sqlInsert(
                'log', [
                    "ip" => $this->ip,
                    "user_api_key" => $this->api_key,
                    "api_version" => $this->version,
                    "endpoint" => $this->endpoint,
                    "parameters" => empty($this->parameters) ? "" : base64_encode(json_encode($this->parameters)),
                    "success" => $success,
                    "status" => $status,
                    "response" => empty($response) ? "" : base64_encode(json_encode($response))
                ]);
            
            http_response_code($status);

            return [
                "success" => $success,
                "status" => $status,
                "response" => $response
            ];

        }

    }