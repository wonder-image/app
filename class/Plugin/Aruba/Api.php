<?php

    namespace Wonder\Plugin\Aruba;

    use Wonder\Api\Call;

    class Api {

        public $endpoint = "https://api.arubabusiness.it";

        protected $username, $password, $apiKey, $otp;

        public $token; 

        function __construct(
            $username = null,
            $password = null,
            $apiKey = null,
            $otp = null
            ) {

            $this->username = is_null($username) ? (isset($_ENV['ARUBA_USERNAME']) ? $_ENV['ARUBA_USERNAME'] : '')  : $username;
            $this->password = is_null($password) ? (isset($_ENV['ARUBA_PASSWORD']) ? $_ENV['ARUBA_PASSWORD'] : '')  : $password;
            $this->apiKey = is_null($apiKey) ? (isset($_ENV['ARUBA_API_KEY']) ? $_ENV['ARUBA_API_KEY'] : '')  : $apiKey;
            $this->otp = is_null($otp) ? (isset($_ENV['ARUBA_OTP']) ? $_ENV['ARUBA_OTP'] : '') : $otp;

        }

        public function auth() {

            $auth = [];
            $auth['grant_type'] = 'password';
            $auth['username'] = $this->username;
            $auth['password'] = $this->password;
            $auth['otp'] = $this->otp;

            $CALL = new Call($this->endpoint."/auth/token", $auth);

            $CALL->method("POST");
            $CALL->contentType("application/x-www-form-urlencoded");
            $CALL->authApiKey($this->apiKey);

            return json_decode($CALL->result(), true);

        }

        public function token($token = null) {

            if ($token == null) {
                $this->token = $this->auth()['access_token'];
            } else {
                $this->token = $token;
            }
            
            return $this->token;
            
        }

        public function call($path) {

            $CALL = new Call($this->endpoint.$path);

            $CALL->method("GET");
            $CALL->authApiKey($this->apiKey);
            $CALL->authBearer($this->token);

            return json_decode($CALL->result(), true);

        }

        public function services() {

            return $this->call('/api/services');

        }

        public function emailList($domain) {

            return $this->call('/api/domains/email/'.$domain.'/box');

        }

        public function aliasEmailList($domain) {

            return $this->call('/api/domains/'.$domain.'/alias/list');

        }

        public function searchDomain($domain, $extension = false) {

            return $this->call('/api/domains/'.$domain.'/whois/'.$extension);

        }

    }