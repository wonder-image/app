<?php

    namespace Wonder\Plugin\Nexi;

    use Wonder\Api\Call;

    class Nexi {

        private $API_KEY, $PROD, $CORRELATION_ID, $ENDPOINT;
        private $ENDPOINT_PROD = "https://xpay.nexigroup.com/api/phoenix-0.0/psp/api/v1";
        private $ENDPOINT_TEST = "https://xpaysandbox.nexigroup.com/api/phoenix-0.0/psp/api/v1";

        function __construct(string $apiKey, bool $prod = true ) {

            $this->API_KEY = $apiKey;
            $this->PROD = $prod;

            $this->CORRELATION_ID = $this->CorrelationId();

            $this->ENDPOINT = $this->PROD ? $this->ENDPOINT_PROD : $this->ENDPOINT_TEST;
            
        }

        private function CorrelationId () {

            $rawCorrelationId = bin2hex(openssl_random_pseudo_bytes(16));

            $correlationId =  substr($rawCorrelationId, 0, 8);
            $correlationId .= "-";
            $correlationId .=  substr($rawCorrelationId, 8, 4);
            $correlationId .= "-";
            $correlationId .=  substr($rawCorrelationId, 12, 4);
            $correlationId .= "-";
            $correlationId .=  substr($rawCorrelationId, 16, 4);
            $correlationId .= "-";
            $correlationId .=  substr($rawCorrelationId, 20);

            return $correlationId;

        }

        public function CallPost ($endpoint, $values = '') {

            $call = new Call($this->ENDPOINT.$endpoint, $values );
            $call->header( 'X-Api-Key: '.$this->API_KEY );
            $call->header( 'Correlation-Id: '.$this->CORRELATION_ID );
            $call->contentType( 'application/json' );

            return json_decode($call->result(), true);
            
        }

        public function CallGet($endpoint, $values = '') {

            $call = new Call($this->ENDPOINT.$endpoint, $values );
            $call->header( 'X-Api-Key: '.$this->API_KEY );
            $call->header( 'Correlation-Id: '.$this->CORRELATION_ID );
            $call->method( 'GET' );

            return json_decode($call->result(), true);
            

        }

        # Documentazione: [ https://developer.nexi.it/it/api/post-orders-hpp ]
        public function Order( array $order ) {

            $response = $this->CallPost('/orders/hpp', $order);

            if (isset($response['hostedPage'])) {

                $return = (object) array();

                $return->securityToken = $response['securityToken'];
                $return->linkPayer = $response['hostedPage'];

                return $return;

            } else {

                return $response;

            }

        }

        # Documentazione: [ https://developer.nexi.it/it/api/get-orders-orderId ]
        public function OrderInfo( $checkoutId ) {

            $response = $this->CallGet('/orders/'.$checkoutId);

            return $response;

        }

    }