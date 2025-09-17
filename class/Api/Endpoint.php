<?php

    namespace Wonder\Api;

    use Wonder\App\Table;

    use Wonder\Api\EndpointException;
    use Wonder\Localization\LanguageContext;

    class Endpoint {

        private $version = "v1.0";
        private $endpoint = "";
        private $auth = [];
        private $requestMethod = "";
        public $token = "";
        public $ip = "";
        public $domain = "";
        public $lang = "";
        public $parameters = [];
        public $user;
        public $userId = "";
        public $userAuth = "";
        public $tokenId = "";

        public function __construct($endpoint, $method = "POST", $auth = [ "api_internal_user", "api_public_access" ]) {

            $LANG = LanguageContext::setLangFromHeader();

            $this->lang = $LANG->getLang();

            $this->endpoint = rtrim($endpoint, '/');
            $this->requestMethod = $method;
            $this->auth = is_array($auth) ? $auth : [ $auth ];

            $this->ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? "";
            $this->domain = $_SERVER['HTTP_HOST'] ?? "";
            
            $this->checkEndpoint();
            $this->checkToken();
            $this->verifyToken();
            $this->authUser();
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

            }

            $this->user = $USER;
            $this->userId = $decoded->sub;
            

        }

        private function authUser() 
        {

            foreach ($this->auth as $auth) {
                if (in_array($auth, (array) $this->user->authority)) {
                    break;
                } else {
                    throw new EndpointException("Utente non autorizzato per questo endpoint!", 403); 
                }
            }
            
            $this->userAuth = $auth;
            $token = $this->user->{$auth};

            if (!$token->exists) {

                throw new EndpointException('Token non valido', 401); 

            } else if ($token->active != 'true') {

                throw new EndpointException('Token non attivo', 401); 

            } else if (!empty($userAuth->allowed_ips) && in_array($this->ip, $token->allowed_ips)) {

                throw new EndpointException('Token non valido per questo IP', 401); 

            } else if (!empty($userAuth->allowed_domains) && in_array($this->ip, $token->allowed_domains)) {

                throw new EndpointException('Token non valido per questo dominio', 401); 

            }

            $this->tokenId = $token->id;

        }

        public function checkParameters(array $parameters): static 
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

            return $this;

        }

        public function response(array|string $response = [], int $status = 200) {

            $success = ($status == 200) ? true : false;

            $VALUES = Table::key('api_activity')
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

            sqlInsert('api_activity', $VALUES);
            
            http_response_code($status);

            return [
                "success" => $success,
                "status" => $status,
                "response" => $response
            ];

        }

    }