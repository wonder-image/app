<?php

    namespace Wonder\Api;

    use Wonder\App\Table;
    use Wonder\Api\EndpointException;
    use Wonder\Localization\LanguageContext;
    use Wonder\Http\UrlParser;

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

        public function __construct($endpoint, $method = "POST", $auth = null) {

            $LANG = LanguageContext::setLangFromHeader();

            $this->lang = $LANG->getLang();

            $this->endpoint = rtrim($endpoint, '/');
            $this->requestMethod = $method;
            $this->auth = $this->resolveAuth($auth);

            $this->ip = UrlParser::requestIp($_SERVER);
            $this->domain = UrlParser::normalizeDomain(UrlParser::requestHost($_SERVER));
            
            $this->checkHttps();
            $this->checkEndpoint();
            $this->checkToken();
            $this->verifyToken();
            $this->authUser();
            $this->checkRequestMethod();
            $this->checkContentType();
            $this->getParameters();

        }

        private function checkHttps(): void
        {

            if (!UrlParser::isHttpsRequest($_SERVER)) {
                throw new EndpointException('Richiesta non sicura: utilizzare HTTPS', 403);
            }

        }

        private function resolveAuth($auth): array
        {

            if ($auth === null || $auth === '' || $auth === []) {
                return array_keys(permissionsApi());
            }

            if (is_array($auth)) {
                $isAssoc = array_keys($auth) !== range(0, count($auth) - 1);
                $values = $isAssoc ? array_keys($auth) : $auth;
            } else {
                $values = [ $auth ];
            }
            $authValues = [];

            foreach ($values as $value) {
                if (!is_string($value) && !is_numeric($value)) {
                    continue;
                }

                $value = trim((string) $value);

                if ($value !== '') {
                    $authValues[] = $value;
                }
            }

            $authValues = array_values(array_unique($authValues));

            return !empty($authValues) ? $authValues : array_keys(permissionsApi());

        }

        private function checkEndpoint() {

            $parsedURI = new UrlParser($_SERVER["REQUEST_URI"] ?? '/');
            $path = $parsedURI->getPath() ?? '';
            $endpointName = rtrim(str_replace('/'.$this->version, "", $path), '/');

            if ($endpointName != $this->endpoint) { 
                throw new EndpointException('Endpoint non trovato! ', 400); 
            }

        }

        private function checkContentType() 
        {

            $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

            // GET/HEAD in genere non portano body e non richiedono Content-Type.
            if (in_array($requestMethod, [ 'GET', 'HEAD' ], true)) {
                $this->contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');
                return;
            }

            $this->contentType = strtolower($_SERVER['CONTENT_TYPE'] ?? '');

            if ($this->contentType === '') {

                $contentLength = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
                $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

                // Nessun body: consenti richieste senza Content-Type.
                if ($contentLength === 0 || in_array($requestMethod, [ 'GET', 'HEAD' ], true)) {
                    return;
                }

                throw new EndpointException('Formato della richiesta non specificato!', 405);

            }

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

            $authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION'] ?? null;

            if (!$authHeader && function_exists('getallheaders')) {
                $headers = getallheaders();
                $authHeader = $headers['Authorization'] ?? $headers['authorization'] ?? null;
            }

            if ($authHeader && preg_match('/Bearer\s(\S+)/', $authHeader, $matches)) {
                
                $this->token = $matches[1];

            } else {
                
                throw new EndpointException('Bearer token mancante!', 401); 

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

            $userAuthority = (array) $this->user->authority;
            $matchedAuth = null;

            foreach ($this->auth as $auth) {
                if (in_array($auth, $userAuthority, true)) {
                    $matchedAuth = $auth;
                    break;
                }
            }

            if ($matchedAuth === null) {
                throw new EndpointException("Utente non autorizzato per questo endpoint!", 403); 
            }
            
            $this->userAuth = $matchedAuth;
            $token = $this->user->{$this->userAuth} ?? null;

            if (!is_object($token) || !isset($token->exists) || !$token->exists) {

                throw new EndpointException('Token non valido', 401); 

            } 
            
            if ($token->active != 'true') {

                throw new EndpointException('Token non attivo', 401); 

            }
            
            $allowedIps = $this->normalizeArray($token->allowed_ips ?? []);

            if (!empty($allowedIps) && !in_array($this->ip, $allowedIps, true)) {

                throw new EndpointException('Token non valido per questo IP', 401); 

            }
            
            $allowedDomains = $this->normalizeArray($token->allowed_domains ?? []);

            if (!empty($allowedDomains) && !UrlParser::matchesAnyDomain($this->domain, $allowedDomains)) {

                throw new EndpointException('Token non valido per questo dominio', 401); 

            }

            $this->tokenId = $token->id ?? null;

        }

        private function normalizeArray($values): array
        {

            if (is_string($values)) {
                $decoded = json_decode($values, true);
                $values = (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) ? $decoded : [ $values ];
            }

            if (!is_array($values)) {
                return [];
            }

            $return = [];

            foreach ($values as $value) {
                if (!is_string($value) && !is_numeric($value)) {
                    continue;
                }

                $value = trim((string) $value);

                if ($value !== '') {
                    $return[] = $value;
                }
            }

            return array_values(array_unique($return));

        }

        private function getParameters()
        {

            $this->data = [];
            $this->files = [];
            $requestMethod = strtoupper($_SERVER['REQUEST_METHOD'] ?? '');

            if ($requestMethod === 'GET') {
                $this->data = UrlParser::requestQueryParameters($_SERVER, is_array($_GET) ? $_GET : []);
                $this->parameters = array_merge($this->data, $this->files);
                return;
            }
            
            if (str_contains($this->contentType, 'application/json')) {

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

        public function validate($parameter, $type = "string", $required = true)
        {

            if ($required && !isset($this->parameters[$parameter])) {
                throw new EndpointException("Parametro $parameter obbligatorio!", 400); 
            }

            if (isset($this->parameters[$parameter])) {

                $value = $this->parameters[$parameter];

                switch ($type) {
                    case 'string':

                        if (is_string($value)) {
                            return $value;
                        } else {
                            throw new EndpointException("Parametro $parameter deve essere una stringa!", 400); 
                        }

                    case 'int':

                        if (filter_var($value, FILTER_VALIDATE_INT) !== false) {
                            return (int) $value;
                        } else {
                            throw new EndpointException("Parametro $parameter deve essere un intero!", 400); 
                        }

                    case 'float':

                        if (filter_var($value, FILTER_VALIDATE_FLOAT) !== false) {
                            return (float) $value;
                        } else {
                            throw new EndpointException("Parametro $parameter deve essere un numero decimale!", 400); 
                        }

                    case 'bool':

                        if (filter_var($value, FILTER_VALIDATE_BOOLEAN) !== false) {
                            return (bool) $value;
                        } else {
                            throw new EndpointException("Parametro $parameter deve essere un booleano!", 400); 
                        }

                    case 'array':

                        if (is_array($value)) {
                            
                            return $value;

                        } elseif (is_string($value)) {

                            $decoded = json_decode($value, true);

                            if (json_last_error() === JSON_ERROR_NONE && is_array($decoded)) {
                                return $decoded;
                            }

                            // In GET supporta anche liste CSV o chiave ripetuta.
                            if (strtoupper($_SERVER['REQUEST_METHOD'] ?? '') === 'GET') {
                                $value = trim($value);

                                if (str_contains($value, ',')) {
                                    $items = array_values(array_filter(
                                        array_map('trim', explode(',', $value)),
                                        fn($item) => $item !== ''
                                    ));

                                    if (!empty($items)) {
                                        return $items;
                                    }
                                }

                                if ($value !== '') {
                                    return [ $value ];
                                }
                            }

                            throw new EndpointException("Parametro $parameter deve essere un array o una stringa JSON decodificabile!", 400); 

                        } else {
                            throw new EndpointException("Parametro $parameter deve essere un array o una stringa JSON decodificabile!", 400); 
                        }

                    default:

                        return $value;

                }
            }

            
            return null;

        }

    }
