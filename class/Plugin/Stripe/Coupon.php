<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class Coupon extends Stripe {

        public function object()
        {
            return parent::connect()->coupons;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($couponId) {

            return $this->object()->update($couponId, $this->params, $this->opts);

        }

        public function get($couponId) {
            
            return $this->object()->retrieve($couponId, $this->params, $this->opts);

        }

        public function delete($couponId)
        {
            
            return $this->object()->delete($couponId, $this->params, $this->opts);

        }

        # https://docs.stripe.com/api/coupons/create?api-version=2025-04-30.basil#create_coupon-name
        public function name($value) {

            return $this->addParams('name', $value);

        }

        # https://docs.stripe.com/api/coupons/create?api-version=2025-04-30.basil#create_coupon-amount_off
        # https://docs.stripe.com/api/coupons/create?api-version=2025-04-30.basil#create_coupon-percent_off
        public function amount($value, $type = 'amount')
        {

            switch ($type) {
                case 'amount':
                    $this->currency('EUR');
                    return $this->addParams('amount_off', $value);
                case 'percent':
                    return $this->addParams('percent_off', $value);
            }

        }

        # https://docs.stripe.com/api/coupons/create?api-version=2025-04-30.basil#create_coupon-currency
        public function currency($value)
        {
            
            return $this->addParams('currency', $value);

        }

        # https://docs.stripe.com/api/coupons/create?api-version=2025-04-30.basil#create_coupon-duration
        public function duration($value) 
        {

            if (in_array($value, [ 'once', 'forever', 'repeating'])) {
                return $this->addParams('duration', $value);
            }

        }

        # https://docs.stripe.com/api/coupons/create?api-version=2025-04-30.basil#create_coupon-max_redemptions
        public function maxUse( int $value) 
        {

            return $this->addParams('max_redemptions', $value);

        }

    }