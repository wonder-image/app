<?php

    namespace Wonder\Plugin\Google\Security;

    use Wonder\Api\Call;
    use Wonder\Plugin\Google\Credentials;

    class reCAPTCHA {

        protected static $projectId;
        protected static $apiKey;
        public static $siteKey;
        protected static $action;
        
        public function __construct() {

            self::$projectId = Credentials::get()->gcp_project_id;;
            self::$apiKey = Credentials::get()->gcp_api_key;
            self::$siteKey = Credentials::get()->recaptcha_site_key;

        }

        public function verify($token, $action):object {
            
            $event = [
                'event' => [
                    'token' => $token,
                    'expectedAction' => $action,
                    'siteKey' => self::$siteKey
                ]
            ];

            $CALL = new Call('https://recaptchaenterprise.googleapis.com/v1/projects/'.self::$projectId.'/assessments?key='.self::$apiKey, $event);
            $CALL->contentType('application/json');

            $RETURN = (object) [];
            $RETURN->result = json_decode($CALL->result(), true);
            $RETURN->valid = isset($RETURN->result['tokenProperties']['valid'] ) && $RETURN->result['tokenProperties']['valid'] == 'true' ? true : false;

            return $RETURN;
        
        }
        
    }