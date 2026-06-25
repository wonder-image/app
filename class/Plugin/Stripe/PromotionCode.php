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

        public function all() {
            
            return $this->object()->all($this->params, $this->opts);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-coupon
        public function coupon($value) {

            return $this->addParams('coupon', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-code
        public function code($value) {

            return $this->addParams('code', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/list?api-version=2025-04-30.basil#list_promotion_codes-active
        public function active(bool $value = true) {

            return $this->addParams('active', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-customer
        public function customer($value) {

            return $this->addParams('customer', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/list?api-version=2025-04-30.basil#list_promotion_codes-limit
        public function limit(int $value) {

            return $this->addParams('limit', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/list?api-version=2025-04-30.basil#list_promotion_codes-starting_after
        public function startingAfter($value) {

            return $this->addParams('starting_after', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/list?api-version=2025-04-30.basil#list_promotion_codes-ending_before
        public function endingBefore($value) {

            return $this->addParams('ending_before', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/list?api-version=2025-04-30.basil#list_promotion_codes-expand
        public function expand(array $value) {

            return $this->addParams('expand', $value);

        }

        # https://docs.stripe.com/api/promotion_codes/create#create_promotion_code-max_redemptions
        public function maxUse(int $value) {

            return $this->addParams('max_redemptions', $value);

        }

    }
