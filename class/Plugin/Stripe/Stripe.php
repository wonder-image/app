<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\StripeClient;
    use Wonder\App\Credentials;

    abstract class Stripe extends StripeClient {

        private $apiKey;

        public $accountId;
        
        public $params, $opts = [];

        public function __construct( $apiKey = null ) {

            $this->apiKey = ($apiKey == null) ? Credentials::api()->stripe_api_key : $apiKey;

            if (!empty(Credentials::api()->stripe_id)) {
                $this->accountId(Credentials::api()->stripe_id);
            }

            parent::__construct($this->apiKey);

        }

        public static function connect($apiKey = null)
        {
            
            return new static($apiKey);

        }

        public function apiKey($apiKey): static { 
            
            $this->apiKey = $apiKey;

            return $this; 
        
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