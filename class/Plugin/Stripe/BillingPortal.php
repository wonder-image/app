<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class BillingPortal extends Stripe {

        public function object()
        {
            return parent::connect()->billingPortal->sessions;

        }

        public function create() 
        {

            return $this->object()->create($this->params, $this->opts);

        }

        public function customerId( $value ) 
        {

            return $this->addParams('customer', $value);

        }
        
        public function configuration( $value ) 
        {

            return $this->addParams('configuration', $value);

        }
 
        public function returnUrl($url) 
        {

            return $this->addParams('return_url', $url);

        }


    }