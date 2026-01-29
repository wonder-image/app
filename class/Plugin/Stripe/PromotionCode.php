<?php

    namespace Wonder\Plugin\Stripe;

    use Wonder\Plugin\Stripe\Stripe;
    
    class PromotionCode extends Stripe {

        public function object()
        {
            return parent::connect()->promotionCodes;

        }

        public function create() {

            return $this->object()->create($this->params, $this->opts);

        }

        public function update($promotionCodeId) {

            return $this->object()->update($promotionCodeId, $this->params, $this->opts);

        }

        public function get($promotionCodeId) {
            
            return $this->object()->retrieve($promotionCodeId, $this->params, $this->opts);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-coupon
        public function coupon($value) {

            return $this->addParams('coupon', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-code
        public function code($value) {

            return $this->addParams('code', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-customer
        public function customer($value) {

            return $this->addParams('customer', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-max_redemptions
        public function maxUse(int $value) {

            return $this->addParams('max_redemptions', $value);

        }

    }
