<?php

    namespace Wonder\Plugin\Nexi;

    use NexiSdk\configuration\IConfiguration;

    class Config implements IConfiguration {

        var $API_KEY = "";

        function __construct( string $apiKey )
        {

            $this->API_KEY = $apiKey;
            
        }

        public function getGatewayBaseUrl(): string
        {
            return "https://stg-ta.nexigroup.com/api/phoenix-0.0/psp/api/v1"; // indirizzo ambiente di test
        }

        public function getApiKey(): string
        {
            return $this->API_KEY;
        }

    }
