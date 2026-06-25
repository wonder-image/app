<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class Subscription extends Stripe {

        
        public function object()
        {
            return parent::connect()->subscriptions;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function get($subscriptionId) {
            
            return $this->object()->retrieve($subscriptionId, $this->params, $this->opts);

        }

        public function update($subscriptionId) {

            return $this->object()->update($subscriptionId, $this->params, $this->opts);

        }

        public function cancelAtPeriodEnd( bool $value = false ) 
        {

            return $this->addParams('cancel_at_period_end', $value);

        }

        # [ https://docs.stripe.com/api/subscriptions/update#update_subscription-cancel_at ]
        public function cancelAt( $value ) 
        {

            return $this->addParams('cancel_at', $value);

        }

    }