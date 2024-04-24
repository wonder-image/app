<?php

    namespace Wonder\Plugin\PayPal;

    class Order {

        public $PAYER = [];
        public $AMOUNT = [];

        public $SHIPPING = [];
        public $ITEMS = [];

        public $CONTEXT = [
            "shipping_preference" => "NO_SHIPPING",
            "landing_page" => "LOGIN",
            "payment_method_preference" => "IMMEDIATE_PAYMENT_REQUIRED"
        ];

        public $CURRENCY = "EUR";

        public $ITEM_TOTAL = 0;
        public $SHIPPING_TOTAL = 0;
        public $TOTAL = 0;

        public $referenceId;

        function __construct($referenceId) {

            $this->referenceId = $referenceId;

        }

        function currency( string $currency ) { $this->CURRENCY = strtoupper($currency); }

        public function Payer( string $name, string $surname, string $email ) {

            $this->PAYER['name'] = [];
            $this->PAYER['name']['given_name'] = $name;
            $this->PAYER['name']['surname'] = $surname;

            $this->PAYER['email_address'] = $email;

        }

        public function Billing( array | object $address ) { $this->PAYER['address'] = $address; }

        public function Shipping( int $value, string $fullName, array $address, string $type = 'SHIPPING' ) { 

            $this->SHIPPING['type'] = $type; 

            $this->SHIPPING['name'] = [];
            $this->SHIPPING['name']['full_name'] = $fullName;

            $this->SHIPPING['address'] = $address; 

            $this->AMOUNT['shipping'] = [];
            $this->AMOUNT['shipping']['currency_code'] = $this->CURRENCY;
            $this->AMOUNT['shipping']['value'] = number_format($value, 2, '.', '');

            $this->CONTEXT['shipping_preference'] = "GET_FROM_FILE";

            $this->TOTAL += $value;
        
        }

        public function Discount(int $value) {
                
            $this->AMOUNT['discount'] = [];
            $this->AMOUNT['discount']['currency_code'] = $this->CURRENCY;
            $this->AMOUNT['discount']['value'] = number_format($value, 2, '.', '');

            $this->TOTAL -= $value;
            
        }

        public function Item( string $name, int $quantity, int $value, string $url = null, string $urlImage = null ) {

            $item = [];
            $item['name'] = $name;
            $item['quantity'] = number_format($quantity, 0, '.', '');

            $item['unit_amount'] = [];
            $item['unit_amount']['currency_code'] = $this->CURRENCY;
            $item['unit_amount']['value'] = number_format($value, 2, '.', '');

            if (!empty($url)) { $item['url'] = $url; }
            if (!empty($urlImage)) { $item['image_url'] = $urlImage; }

            $this->TOTAL += $value;
            $this->ITEM_TOTAL += $value;

            array_push($this->ITEMS, $item);

        }

        public function Context( $brandName, $returnUrl, $cancelUrl ) {

            $this->CONTEXT['brand_name'] = $brandName;
            $this->CONTEXT['return_url'] = $returnUrl;
            $this->CONTEXT['cancel_url'] = $cancelUrl;

        }

        public function Create() {

            $order = [];

            $order['intent'] = "CAPTURE";
            $order['payer'] = $this->PAYER;
            
            $order['payment_source'] = [];
            $order['payment_source']['paypal'] = [];
            $order['payment_source']['paypal']['experience_context'] = $this->CONTEXT;

            $order['purchase_units'] = [];

            $orderValue = [];

            $orderValue['reference_id'] = $this->referenceId;

            $orderValue['amount'] = [];

            $orderValue['amount']['currency_code'] = $this->CURRENCY;
            $orderValue['amount']['value'] = number_format($this->TOTAL, 2, '.', '');
            
            $orderValue['amount']['breakdown'] = $this->AMOUNT;

            $orderValue['amount']['breakdown']['item_total'] = [];
            $orderValue['amount']['breakdown']['item_total']['currency_code'] = $this->CURRENCY;
            $orderValue['amount']['breakdown']['item_total']['value'] = number_format($this->ITEM_TOTAL, 2, '.', '');

            if (!empty($this->SHIPPING)) { $orderValue['shipping'] = $this->SHIPPING; }

            $orderValue['items'] = $this->ITEMS;

            array_push($order['purchase_units'], $orderValue);

            return $order;

        }

    }