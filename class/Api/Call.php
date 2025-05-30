<?php

    namespace Wonder\Api;

    class Call {

        private $endpoint = "";

        public $headers = [];
        public $method = "POST";
        public $type, $values;

        private $cURL = "";

        function __construct( string $endpoint, array|string $values = "") {
            
            $this->endpoint = $endpoint;
            $this->values = $values;

            # Inizio connessione
            $this->cURL = curl_init();

        }

        function method($method) { $this->method = strtoupper($method); }

        function header($value) { array_push($this->headers, $value); }

        function contentType($type) {

            $this->header('Content-Type: '.$type);
            $this->type = $type;

            if ($type == 'application/json') {
                $this->values = empty($this->values) ? "" : json_encode($this->values);
            } else if ($type == 'application/x-www-form-urlencoded' && $this->method == 'POST') {
                $this->values = http_build_query($this->values);
            }

        }

        function authBasic( string $username, string $password ) {

            curl_setopt($this->cURL, CURLOPT_USERPWD, "$username:$password");

        }

        function authApiKey( string $apiKey ) {

            $this->header('Authorization-Key: '.$apiKey);

        }

        function authBearer( string $token) {

            $this->header('Authorization: Bearer '.$token);

        }

        function result() {

            # Metodo invio
                if ($this->method == 'POST') {

                    curl_setopt($this->cURL, CURLOPT_POST, true);
                    curl_setopt($this->cURL, CURLOPT_POSTFIELDS, $this->values);
                    
                } else if ($this->method == 'GET') {
                    
                    if (!empty($this->values)) {
                        $this->endpoint .= "?".http_build_query($this->values);
                    }
                    
                } else if ($this->method == 'PATCH') {

                    curl_setopt($this->cURL, CURLOPT_CUSTOMREQUEST, "PATCH");
                    curl_setopt($this->cURL, CURLOPT_POSTFIELDS, $this->values);
                    
                }
            
            # Connessione
                curl_setopt($this->cURL, CURLOPT_URL, $this->endpoint);
                
            # Header
                if (!empty($this->headers)) {

                    curl_setopt($this->cURL, CURLOPT_HTTPHEADER, $this->headers);

                }

            # Chiedi risposta
                curl_setopt($this->cURL, CURLOPT_RETURNTRANSFER, true);

            # Risultato
                $result = curl_exec($this->cURL);
                curl_close($this->cURL);
            
            # Verifica errori
                if (curl_errno($this->cURL)) {

                    $errno = curl_errno($this->cURL); 
                    return curl_strerror($errno);
                    
                } else {

                    return $result;

                }
                
        }

    }