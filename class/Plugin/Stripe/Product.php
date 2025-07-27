<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\SearchResult;
    use Wonder\Plugin\Stripe\Stripe;
    
    class Product extends Stripe {

        public function object()
        {
            return parent::connect()->products;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($productId) {

            return $this->object()->update($productId, $this->params, $this->opts);

        }

        public function get($productId) {
            
            return $this->object()->retrieve($productId, $this->params, $this->opts);

        }

        public function delete($productId)
        {
            
            return $this->object()->delete($productId, $this->params, $this->opts);

        }

        public function search($query, $limit = 10): SearchResult 
        {
            
            return $this->object()->search([ 'query' => $query, 'limit' => $limit ], $this->opts);

        }

        public function name($value) {

            return $this->addParams('name', $value);

        }

        public function active($value) {

            return $this->addParams('active', $value);

        }

        public function description($value) {

            return $this->addParams('description', $value);

        }

        public function url($value) {

            return $this->addParams('url', $value);

        }

        public function image( array $value) {

            return $this->pushParams('images', $value);

        }

        public function images( array $value) {

            return $this->addParams('images', $value);

        }

        public function searchCustomId($id) {

            return $this->search( "metadata['id']:'$id'", 1 );

        }

    }