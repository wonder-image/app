<?php

    namespace Wonder\Api;

    use Wonder\App\Table;
    use Wonder\Api\EndpointException;
    use Wonder\Localization\LanguageContext;

    class Endpoint {

        // Endpoint
        private $version = "v1.0";
        private $endpoint = "";
        private $auth = [];

        // Token
        private $token, $tokenId;

        // Request
        private $ip, $domain, $lang, $requestMethod, $contentType;
        public $parameters, $data, $files = [];

        // User
        private $user, $userId, $userAuth;

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
            $this->getParameters();

        }

        private function checkEndpoint() {

            $parsedURI = parse_url($_SERVER["REQUEST_URI"]);
            $endpointName = rtrim(str_replace('/'.$this->version, "", $parsedURI["path"]), '/');

            if ($endpointName != $this->endpoint) { 
                throw new EndpointException('Endpoint non trovato! ', 400); 
            }

        }

        private function checkContentType() 
        {

            $requestMethod = $_SERVER['REQUEST_METHOD'] ?? '';

            if (in_array($requestMethod, [ 'GET', 'HEAD' ], true)) {
                $this->contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
                return;
            }

            if (!isset($_SERVER['CONTENT_TYPE'])) {
                throw new EndpointException('Formato della richiesta non specificato!', 405);
            }

            $this->contentType = strtolower($_SERVER['CONTENT_TYPE']);

            if (!str_contains($this->contentType, 'application/json') && 
                !str_contains($this->contentType, 'multipart/form-data')) {
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
            $token = $this->user->{$this->userAuth};

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

        private function getParameters()
        {

            $this->data = [];
            $this->files = [];
            
            if (($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {

                $this->data = $_GET;

            } elseif (str_contains($this->contentType, 'application/json')) {

                $input = file_get_contents('php://input');

                $this->data = $input ? json_decode($input, true) : [];

                if (json_last_error()) {
                    throw new EndpointException('Errore nel formato JSON della richiesta!', 400);
                }

            } elseif (str_contains($this->contentType, 'multipart/form-data')) {

                $this->data = $_POST;
                $this->files = $_FILES; 

            }

            $this->parameters = array_merge($this->data, $this->files);

        }

        public function checkParameters( array $parameters ): static 
        {

            if (is_array($this->parameters)) {
                
                foreach ($parameters as $parameter) {
                    if (!array_key_exists($parameter, $this->parameters)) {
                        throw new EndpointException("Parametro $parameter obbligatorio!", 401); 
                    }
                }

            }

            return $this;

        }

        private function logFiles()
        {

            $logFiles = [];

            foreach ($this->files as $key => $file) {
                // File singolo
                if (is_array($file) && !is_array($file['name'])) {
                    if ($file['error'] === UPLOAD_ERR_OK) {
                        $logFiles[$key] = [
                            'name' => $file['name'],
                            'type' => $file['type'],
                            'size' => $file['size'],
                        ];
                    }
                }

                // File multiplo
                elseif (is_array($file['name'])) {
                    $logFiles[$key] = [];
                    foreach ($file['name'] as $i => $filename) {
                        if ($file['error'][$i] === UPLOAD_ERR_OK) {
                            $logFiles[$key][] = [
                                'name' => $filename,
                                'type' => $file['type'][$i],
                                'size' => $file['size'][$i],
                            ];
                        }
                    }
                }
            }

            return $logFiles;

        }

        public function response(array|string $response = [], int $status = 200): array 
        {

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
                                "request_method" => $this->requestMethod,
                                "content_type" => $this->contentType,
                                "parameters" => json_encode(array_merge($this->data, $this->logFiles()), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                "data" => json_encode($this->data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                "files" => json_encode($this->logFiles(), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                                "success" => $success,
                                "status" => $status,
                                "response" => json_encode($response, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
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
    