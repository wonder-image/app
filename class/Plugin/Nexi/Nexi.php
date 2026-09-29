<?php

    namespace Wonder\Plugin\Nexi;

    use Wonder\Api\Call;
    
    /**
     * 
     * Link utili:
     * - ApiKey test [ https://developer.nexi.it/it/area-test/api-key ]
     * - Carte test [ https://developer.nexi.it/it/area-test/carte-di-pagamento ]
     * 
     */

     class Nexi {

        private $API_KEY, $PROD, $CORRELATION_ID, $ENDPOINT, $ALIAS, $MAC_KEY;
        private $ENDPOINT_PROD = "https://xpay.nexigroup.com/api/phoenix-0.0/psp/api/v1";
        private $ENDPOINT_TEST = "https://xpaysandbox.nexigroup.com/api/phoenix-0.0/psp/api/v1";

        function __construct(string $apiKey = '', bool $prod = true, string $alias = '', string $macKey = '')
        {

            $this->API_KEY = $apiKey;
            $this->PROD = $prod;
            $this->ALIAS = trim($alias);
            $this->MAC_KEY = trim($macKey);

            $this->CORRELATION_ID = $this->CorrelationId();

            $this->ENDPOINT = $this->PROD ? $this->ENDPOINT_PROD : $this->ENDPOINT_TEST;
            
        }

        public function alias(): string
        {
            return $this->ALIAS;
        }

        public function macKey(): string
        {
            return $this->MAC_KEY;
        }

        public function hasApiCredentials(): bool
        {
            return trim((string) $this->API_KEY) !== '';
        }

        public function hasClassicCredentials(): bool
        {
            return $this->ALIAS !== '' && $this->MAC_KEY !== '';
        }

        /**
         * Firma l'avvio di un pagamento XPay classico.
         *
         * Formula Nexi: SHA1("codTrans=<...>divisa=<...>importo=<...><chiave>").
         */
        public function classicPaymentMac(string $transactionCode, string $currency, int|string $amount): string
        {
            return sha1(
                'codTrans='.$transactionCode
                .'divisa='.$currency
                .'importo='.$amount
                .$this->requiredMacKey()
            );
        }

        /** Verifica il MAC della notifica/esito XPay classico. */
        public function verifyClassicResultMac(array $result, string $mac): bool
        {
            $payload = '';

            foreach (['codTrans', 'esito', 'importo', 'divisa', 'data', 'orario', 'codAut'] as $field) {
                $payload .= $field.'='.(string) ($result[$field] ?? '');
            }

            return hash_equals(sha1($payload.$this->requiredMacKey()), strtolower(trim($mac)));
        }

        private function requiredMacKey(): string
        {
            if ($this->MAC_KEY === '') {
                throw new \RuntimeException('Chiave MAC Nexi non configurata.');
            }

            return $this->MAC_KEY;
        }

        private function CorrelationId () 
        {

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
