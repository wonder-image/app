<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class Checkout {

        public $params, $opts = [];

        public function create() {

            return (new Stripe)->checkout->sessions->create($this->params, $this->opts);

        }

        public function get($sessionId) {
            
            return (new Stripe)->checkout->sessions->retrieve($sessionId, $this->params, $this->opts);

        }

        public function delete($sessionId) {
            
            return (new Stripe)->checkout->sessions->expire($sessionId, $this->params, $this->opts);

        }

        public function item($name, $price, $quantity = 1, $currency = 'eur') {
            
            if (!isset($this->params['line_items'])) { $this->params['line_items'] = []; }

            array_push($this->params['line_items'], [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => number_format($price, 2, '', ''), # in centesimi
                    'product_data' => [
                        'name' => $name,
                    ],
                ],
                'quantity' => $quantity,
            ]);

            return $this;

        }

        public function itemId($priceId, $quantity = 1) {
            
            if (!isset($this->params['line_items'])) { $this->params['line_items'] = []; }

            array_push($this->params['line_items'], [
                'price' => $priceId,
                'quantity' => $quantity,
            ]);

            return $this;

        }

        public function mode($mode) {

            $this->params['mode'] = $mode;

            return $this;

        }

        public function returnUrl($url) {

            # {CHECKOUT_SESSION_ID}
            $this->params['return_url'] = $url;

            return $this;

        }

        public function successUrl($url) {

            # {CHECKOUT_SESSION_ID}
            $this->params['success_url'] = $url;

            return $this;

        }

        public function cancelUrl($url) {

            # {CHECKOUT_SESSION_ID}
            $this->params['cancel_url'] = $url;

            return $this;

        }

        public function accountId($accountId) {
            
            $this->opts['stripe_account'] = $accountId;

            return $this;

        }

        public function customField($key, $label, $type = 'text', $text = []) {

            if (!isset($this->params['custom_fields'])) { $this->params['custom_fields'] = []; }

            array_push($this->params['custom_fields'], [
                'key' => $key,
                'label' => [
                    'type' => 'custom',
                    'custom' => $label
                ],
                'type' => $type,
                $type => $text
            ]);

            return $this;

        }

        public function paymentMethod($type) {

            if (!isset($this->params['payment_method_types'])) { $this->params['payment_method_types'] = []; }

            array_push($this->params['payment_method_types'], $type);

            return $this;

        }

        public function phone($enabled = true) {

            $this->params['phone_number_collection']['enabled'] = $enabled;

            return $this;

        }

        public function email($value) {

            $this->params['customer_email'] = $value;

            return $this;

        }


        public function billingAddress($required = true) {

            $this->params['billing_address_collection'] = $required ? 'required' : 'auto';

            return $this;

        }

        public function tax($bool = true) {

            $this->params['tax_id_collection']['enabled'] = $bool;

            return $this;
            
        }

        public function shippingAddress($allowedCountries = []) {

            $this->params['shipping_address_collection']['allowed_countries'] = $allowedCountries;

            return $this;

        }

        public function metadata($key, $value) {

            if (!isset($this->params['metadata'])) { $this->params['metadata'] = []; }

            $this->params['metadata'][$key] = $value;

            return $this;

        }

    }