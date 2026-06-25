<?php

    namespace Wonder\Plugin\Stripe;

    use Stripe\SearchResult;
    use Wonder\Plugin\Stripe\Stripe;
    use Wonder\Plugin\Stripe\Product;
    
    class ProductPrice extends Stripe {

        public function object()
        {
            
            return parent::connect()->prices;

        }

        public function create() 
        {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($productId) 
        {

            return $this->object()->update($productId, $this->params, $this->opts);

        }

        public function get($productId) 
        {
            
            return $this->object()->retrieve($productId, $this->params, $this->opts);

        }

        public function search($query, $limit = 10): SearchResult 
        {
            
            return $this->object()->search([ 'query' => $query, 'limit' => $limit ], $this->opts);

        }

        # https://docs.stripe.com/api/prices/create?api-version=2025-04-30.basil#create_price-product_data
        public function product( Product $product )
        {

            return $this->addParams('product_data', $product->params );

        }

        # https://docs.stripe.com/api/prices/create?api-version=2025-04-30.basil#create_price-active
        public function active($value) 
        {

            return $this->addParams('active', $value);

        }

        # https://docs.stripe.com/api/prices/create?api-version=2025-04-30.basil#create_price-unit_amount
        public function amount( int $value )
        {

            return $this->addParams('unit_amount', $value);

        }

        # https://docs.stripe.com/api/prices/create?api-version=2025-04-30.basil#create_price-currency
        public function currency($value)
        {

            return $this->addParams('currency', $value);

        }

        public function searchCustomId($id) {

            return $this->search( "metadata['id']:'$id'", 1 );

        }

    }