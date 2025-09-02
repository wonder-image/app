<?php

    namespace Wonder\Api;

    use Wonder\App\Table;

    use Wonder\Api\EndpointException;

    class Endpoint {

        private $version = "v1.0";
        private $endpoint = "";
        private $requestMethod = "";
        public $token = "";
        public $ip = "";
        public $domain = "";
        public $parameters = [];
        public $userId = "";
        public $tokenId = "";

        public function __construct($endpoint = "", $requestMethod = "POST") {

            $this->endpoint = rtrim($endpoint, '/');
            $this->requestMethod = $requestMethod;

            $this->ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? "";
            $this->domain = $_SERVER['HTTP_HOST'] ?? "";
            
            $this->checkEndpoint();
            $this->checkToken();
            $this->verifyToken();
            $this->checkContentType();
            $this->checkRequestMethod();

        }

        private function checkEndpoint() {

            $parsedURI = parse_url($_SERVER["REQUEST_URI"]);
            $endpointName = rtrim(str_replace('/'.$this->version, "", $parsedURI["path"]), '/');
            
            if ($endpointName != $this->endpoint) { 
                throw new EndpointException('Endpoint non trovato! ', 400); 
            }

        }

        private function checkContentType() {

            if (!isset($_SERVER['CONTENT_TYPE']) || $_SERVER['CONTENT_TYPE'] != 'application/json') { 
                throw new EndpointException('Formato della richiesta non valido!', 405); 
            }

        }

        private function checkRequestMethod() {
            
            if (!isset($_SERVER['REQUEST_METHOD']) || $_SERVER['REQUEST_METHOD'] != $this->requestMethod) { 
                throw new EndpointException("Metodo della richiesta non valido! Utilizzare $this->requestMethod", 405); 
            }

        }

        private function checkToken() {

            if (!isset($_SERVER['HTTP_AUTHORIZATION']) || $_SERVER['HTTP_AUTHORIZATION'] == '') {

                throw new EndpointException('Bearer token mancante!', 401); 

            } else {

                $this->token = str_replace("Bearer ", "", $_SERVER['HTTP_AUTHORIZATION']);

            }

        }

        private function verifyToken() {

            $decoded = \Firebase\JWT\JWT::decode(
                $this->token, 
                new \Firebase\JWT\Key(\Wonder\App\Credentials::appKey(), 'HS256')
            );

            $USER = infoUser($decoded->sub);

            if (!$USER->exists) {

                throw new EndpointException('Utente non valido', 401); 

            } else if ($USER->active != 'true') {

                throw new EndpointException('Utente non attivo', 401); 

            } else if (!$USER->api->exists) {

                throw new EndpointException('Token non valido', 401); 

            } else if ($USER->api->active != 'true') {

                throw new EndpointException('Token non attivo', 401); 

            } else if (!empty($USER->api->allowed_ips) && in_array($this->ip, $USER->api->allowed_ips)) {

                throw new EndpointException('Token non valido per questo IP', 401); 

            } else if (!empty($USER->api->allowed_domains) && in_array($this->ip, $USER->api->allowed_domains)) {

                throw new EndpointException('Token non valido per questo dominio', 401); 

            }

            $this->userId = $USER->id;
            $this->tokenId = $USER->api->id;

        }

        public function checkParameters(array $parameters) 
        {

            $this->parameters = json_decode(file_get_contents('php://input'), true);
            
            if (json_last_error()) {
                throw new EndpointException('Errore nel formato della richiesta! Verificare i parametri.', 401); 
            }

            if (is_array($parameters)) {
                
                foreach ($parameters as $parameter) {
                    if (!array_key_exists($parameter, $this->parameters)) {
                        throw new EndpointException("Parametro $parameter obbligatorio!", 401); 
                    }
                }

            }

        }

        public function response(array|string $response = [], int $status = 200) {

            $success = ($status == 200) ? true : false;

            $VALUES = Table::key('api_log')
                            ->prepare([
                                    "user_id" => $this->userId,
                                    "token_id" => $this->tokenId,
                                    "token" => $this->token,
                                    "ip" => $this->ip,
                                    "domain" => $this->domain,
                                    "version" => $this->version,
                                    "endpoint" => $this->endpoint,
                                    "parameters" => $this->parameters,
                                    "success" => $success,
                                    "status" => $status,
                                    "response" => $response
                            ]);

            sqlInsert('api_log', $VALUES);
            
            http_response_code($status);

            return [
                "success" => $success,
                "status" => $status,
                "response" => $response
            ];

        }

    }