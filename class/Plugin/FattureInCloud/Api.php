<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\App\Credentials;
    use FattureInCloud\Configuration;
    use FattureInCloud\Api\{ UserApi, ClientsApi, InfoApi, SettingsApi };
    use GuzzleHttp\Client as GuzzleClient;

    class Api {

        private static $token;
        private static $config;
        public static $companyId;

        public function __construct($token = null, $companyId = null) 
        {

            self::$token = ($token == null) ? Credentials::api()->fatture_in_cloud_token : $token;
            self::$companyId = ($companyId == null) ? Credentials::api()->fatture_in_cloud_company_id : $companyId;
            self::$config = Configuration::getDefaultConfiguration()->setAccessToken(self::$token);

        }

        public static function connect($token = null): static
        {
            
            return new static($token);

        }

        public function user(): UserApi {

            return new UserApi( new GuzzleClient(), self::$config );

        }

        public function client(): ClientsApi {

            return new ClientsApi( new GuzzleClient(), self::$config );

        }

        public function info(): InfoApi {

            return new InfoApi( new GuzzleClient(), self::$config );

        }

        public function settings(): SettingsApi {

            return new SettingsApi( new GuzzleClient(), self::$config );

        }

    }