<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\StripeClient;
    use Wonder\App\Credentials;

    class Stripe extends StripeClient {

        private $apiKey;

        public function __construct( $apiKey = null ) {

            $this->apiKey = ($apiKey == null) ? Credentials::api()->stripe_api_key : $apiKey;

            parent::__construct($this->apiKey);

        }

    }