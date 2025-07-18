<?php

    namespace Wonder\Api;

    use CurlHandle;

    class Call {

        private $endpoint = "";

        public $headers = [];
        public $method = "POST";
        public $type, $values;

        private bool|CurlHandle $cURL;

        public function __construct( string $endpoint, array|string $values = "") {
            
            $this->endpoint = $endpoint;
            $this->values = $values;

            # Inizio connessione
            $this->cURL = curl_init();

        }

        public function method($method): static 
        { 
            
            $this->method = strtoupper($method);
            return $this;
        
        }

        public function header($value): static  
        {
            
            array_push($this->headers, $value); 
            return $this;

        }

        public function contentType($type): static 
        {

            $this->header('Content-Type: '.$type);
            $this->type = $type;

            if ($type == 'application/json') {
                $this->values = empty($this->values) ? "" : json_encode($this->values);
            } else if ($type == 'application/x-www-form-urlencoded' && $this->method == 'POST') {
                $this->values = http_build_query($this->values);
            }

            return $this;

        }

        public function authBasic( string $username, string $password ): static 
        {

            curl_setopt($this->cURL, CURLOPT_USERPWD, "$username:$password");
            return $this;

        }

        public function authApiKey( string $apiKey ): static 
        {

            $this->header('Authorization-Key: '.$apiKey);
            return $this;

        }

        public function authBearer( string $token): static 
        {

            $this->header('Authorization: Bearer '.$token);
            return $this;

        }

        public function result() {

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