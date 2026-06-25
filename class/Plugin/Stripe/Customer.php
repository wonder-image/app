<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\SearchResult;
    use Wonder\Plugin\Stripe\Stripe;
    
    class Customer extends Stripe {

        public function object()
        {
            return parent::connect()->customers;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($customerId) {

            return $this->object()->update($customerId, $this->params, $this->opts);

        }

        public function get($customerId) {
            
            return $this->object()->retrieve($customerId, $this->params, $this->opts);

        }

        public function delete($customerId)
        {
            
            return $this->object()->delete($customerId, $this->params, $this->opts);

        }

        public function search($query, $limit = 10): SearchResult 
        {
            
            return $this->object()->search([ 'query' => $query, 'limit' => $limit ], $this->opts);

        }

        public function name($value) {

            return $this->addParams('name', $value);

        }

        public function email($value) {

            return $this->addParams('email', $value);

        }

        public function phone($value) {

            return $this->addParams('phone', $value);

        }

        public function address($country, $state, $postalCode, $city, $line1, $line2 = '') {

            return $this->addParams('address', [
                'country' => $country,
                'state' => $state,
                'postal_code' => $postalCode,
                'city' => $city,
                'line1' => $line1,
                'line2' => $line2
            ]);

        }

        public function shipping($name, $phone, $country, $state, $postalCode, $city, $line1, $line2 = '') {

            return $this->addParams('shipping', [
                'name' => $name,
                'phone' => $phone,
                'address' => [
                    'country' => $country,
                    'state' => $state,
                    'postal_code' => $postalCode,
                    'city' => $city,
                    'line1' => $line1,
                    'line2' => $line2
                ]
            ]);

        }

        public function lang( $country ) {

            return $this->pushParams('preferred_locales', $country);

        }

        public function searchCustomId($id) {

            return $this->search( "metadata['id']:'$id'", 1 );

        }

    }