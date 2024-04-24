<?php

    namespace Wonder\Plugin\PayPal;

    use Wonder\Api\Call;
    
    class PayPal {

        private $VERSION = 2;
        private $LIVE, $ENDPOINT;

        private $ENDPOINT_LIVE = "https://api-m.paypal.com";
        private $ENDPOINT_SANDBOX = "https://api-m.sandbox.paypal.com";

        private $CLIENT_ID = "";
        private $CLIENT_SECRET = "";
        public $TOKEN = "";

        function __construct( string $clientId, string $clientSecret, bool $live = true ) {

            $this->CLIENT_ID = $clientId;
            $this->CLIENT_SECRET = $clientSecret;
            $this->LIVE = $live;

            $this->ENDPOINT = $this->LIVE ? $this->ENDPOINT_LIVE : $this->ENDPOINT_SANDBOX;
            
            $this->Token();

        }

        # Documentazione: https://developer.paypal.com/api/rest/
        private function Token() {

            $call = new Call($this->ENDPOINT."/v1/oauth2/token", [ "grant_type" => "client_credentials" ]);
            $call->authBasic( $this->CLIENT_ID, $this->CLIENT_SECRET );
            $call->contentType('application/x-www-form-urlencoded');

            $response = json_decode($call->result(), true);

            if (isset($response['access_token'])) {
                $this->TOKEN = $response['access_token'];
            } else {
                return $response;
            }

        }

        # Documentazione: https://developer.paypal.com/docs/api/orders/v2/
        public function Order( array $order ) {

            $call = new Call($this->ENDPOINT."/v2/checkout/orders", $order );

            $call->authBearer( $this->TOKEN );
            $call->contentType('application/json');

            $response = json_decode($call->result(), true);

            if (isset($response['status']) && $response['status'] == 'PAYER_ACTION_REQUIRED') {

                $return = (object) array();

                $return->id = $response['id'];
                $return->status = $response['status'];

                foreach ($response['links'] as $key => $array) {
                    if ($array['rel'] == 'self') { $return->linkSelf = $array['href']; }
                    if ($array['rel'] == 'payer-action') { $return->linkPayer = $array['href']; }
                }

                return $return;

            } else {

                return $response;

            }

        }

        public function ConfirmOrder( $checkoutId ) {

            $call = new Call($this->ENDPOINT."/v2/checkout/orders/".$checkoutId ."/capture" );

            $call->authBearer( $this->TOKEN );
            $call->contentType('application/json');

            return json_decode($call->result(), true);

        }

        public function OrderInfo( $checkoutId ) {

            $call = new Call($this->ENDPOINT."/v2/checkout/orders/".$checkoutId );

            $call->authBearer( $this->TOKEN );
            $call->method('GET');

            return json_decode($call->result(), true);

        }

    }