<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\ApiRequestor;
    use Stripe\HttpClient\ClientInterface;
    use Stripe\HttpClient\CurlClient;
    use Stripe\StripeClient;
    use Wonder\App\Credentials;

    abstract class Stripe extends StripeClient {

        # Versione dell'API fissata: l'SDK 19 porterebbe a clover, che toglie per esempio `coupon` dai codici promozionali
        public const API_VERSION = '2025-08-27.basil';

        public const MAX_NETWORK_RETRIES = 2;

        public const TIMEOUT = 20;

        public const CONNECT_TIMEOUT = 5;

        private static $apiKey;

        private static ?ClientInterface $configuredHttpClient = null;

        public $accountId;
        
        public $params, $opts = [];

        public function __construct( $apiKey = null ) {

            self::$apiKey = ($apiKey == null) ? Credentials::api()->stripe_api_key : $apiKey;

            if (!empty(Credentials::api()->stripe_id)) {
                $this->accountId(Credentials::api()->stripe_id);
            }

            parent::__construct(self::clientConfig(self::$apiKey));

        }

        /**
         * Config di ogni StripeClient del plugin: versione API fissata e
         * retry di rete, con il client HTTP già preparato da httpClient().
         */
        public static function clientConfig( ?string $apiKey ): array
        {

            self::httpClient();

            return [
                'api_key' => $apiKey,
                'stripe_version' => self::API_VERSION,
                'max_network_retries' => self::MAX_NETWORK_RETRIES,
            ];

        }

        /**
         * Client HTTP condiviso dell'SDK, con timeout di 20 s (5 per la
         * connessione) invece di 80/30. Un client già installato che non è
         * CurlClient (per esempio un fake nei test) resta com'è. Alza anche i
         * retry globali: solo così CurlClient mette l'Idempotency-Key alle
         * POST v1 che non ne hanno una, e il retry non le duplica.
         */
        public static function httpClient(): ClientInterface
        {

            if (\Stripe\Stripe::getMaxNetworkRetries() < self::MAX_NETWORK_RETRIES) {
                \Stripe\Stripe::setMaxNetworkRetries(self::MAX_NETWORK_RETRIES);
            }

            $client = ApiRequestor::httpClient();

            if ($client instanceof CurlClient && $client !== self::$configuredHttpClient) {
                $client->setTimeout(self::TIMEOUT);
                $client->setConnectTimeout(self::CONNECT_TIMEOUT);
                self::$configuredHttpClient = $client;
            }

            return $client;

        }

        public static function connect($apiKey = null)
        {
            
            return new static($apiKey);

        }

        public static function apiKey($apiKey): static { 
            
            self::$apiKey = $apiKey;

            return new static($apiKey); 
        
        }

        public function addParams($key, $value): static
        {

            $keys = explode('.', $key);
            $target = &$this->params;

            foreach ($keys as $part) {
                
                if (!isset($target[$part]) || !is_array($target[$part])) {
                    $target[$part] = [];
                }

                $target = &$target[$part];

            }

            $target = $value;

            return $this;

        }
        
        public function pushParams($key, $value): static 
        {

            $keys = explode('.', $key);
            $target = &$this->params;

            foreach ($keys as $part) {
                
                if (!isset($target[$part]) || !is_array($target[$part])) {
                    $target[$part] = [];
                }

                $target = &$target[$part];

            }
            
            array_push($target, $value);

            return $this;

        }

        public function addOptions($key, $value): static
        {

            $this->opts[$key] = $value;

            return $this;

        }

        public function accountId($accountId): static
        { 

            $this->accountId = $accountId;
            
            return $this->addOptions('stripe_account', $accountId); 
        
        }

        public function customId($id): static
        {

            return $this->metadata('id', $id);

        }

        public function metadata($key, $value): static
        {

            return $this->addParams("metadata.$key", $value );

        }


    }