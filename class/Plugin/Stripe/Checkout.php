<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    use Wonder\Plugin\Stripe\ProductPrice;
    
    class Checkout extends Stripe {

        
        public function object()
        {
            return parent::connect()->checkout->sessions;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function get($sessionId) {
            
            return $this->object()->retrieve($sessionId, $this->params, $this->opts);

        }

        public function delete($sessionId) {
            
            return $this->object()->expire($sessionId, $this->params, $this->opts);

        }

        public function itemPrice( ProductPrice $productPrice, $quantity)
        {

            return $this->pushParams('line_items', [
                'price_data' => $productPrice->params,
                'quantity' => $quantity,
            ]);

        }

        public function item($name, $price, $quantity = 1, $currency = 'eur', $images = []) {

            return $this->pushParams('line_items', [
                'price_data' => [
                    'currency' => $currency,
                    'unit_amount' => number_format($price, 2, '', ''), # in centesimi
                    'product_data' => [
                        'name' => $name,
                        'images' => $images
                    ],
                ],
                'quantity' => $quantity,
            ]);

        }

        public function customerId( $value ) 
        {

            return $this->addParams('customer', $value);

        }


        public function couponId( $value ) {

            return $this->pushParams('discounts', [ 'coupon' => $value ]);

        }


        public function itemId($priceId, $quantity = 1, array $taxRate = []) {

            return $this->pushParams('line_items', [
                'price' => $priceId,
                'quantity' => $quantity,
                'tax_rates' => $taxRate
            ]);

        }

        public function mode($mode) {

            return $this->addParams('mode', $mode);

        }

        public function returnUrl($url) {

            # {CHECKOUT_SESSION_ID}
            return $this->addParams('return_url', $url);

        }

        public function successUrl($url) {

            # {CHECKOUT_SESSION_ID}
            return $this->addParams('success_url', $url);

        }

        public function cancelUrl($url) {

            # {CHECKOUT_SESSION_ID}
            return $this->addParams('cancel_url', $url);

        }

        public function customField($key, $label, $type = 'text', $text = []) {

            return $this->addParams('custom_fields', [
                'key' => $key,
                'label' => [
                    'type' => 'custom',
                    'custom' => $label
                ],
                'type' => $type,
                $type => $text
            ]);

        }

        public function paymentMethods( array $types ) {

            return $this->addParams('payment_method_types', $types);

        }

        public function paymentMethod($type) {

            return $this->pushParams('payment_method_types', $type);

        }

        public function phone( bool $enabled = true) {

            return $this->addParams('phone_number_collection.enabled', $enabled);

        }

        public function email($value) {

            return $this->addParams('customer_email', $value);

        }

        public function billingAddress( bool $required = true) {

            return $this->addParams('billing_address_collection', $required ? 'required' : 'auto');

        }

        public function tax( bool $enabled = true) {

            return $this->addParams('tax_id_collection.enabled', $enabled);
            
        }

        public function shippingAddress($allowedCountries = []) {

            return $this->addParams('shipping_address_collection.allowed_countries', $allowedCountries);

        }

        public function shipping( int $price, $name = 'Spedizione')
        {
            return $this->pushParams('shipping_options', [
                'shipping_rate_data' => [
                    'display_name' => $name,
                    'type' => 'fixed_amount',
                    'fixed_amount' => [
                        'amount' => $price,
                        'currency' => 'EUR'
                    ]
                    ]
                ]);

        }

    }