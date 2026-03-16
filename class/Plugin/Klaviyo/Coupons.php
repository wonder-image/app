<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\CouponsApi
     */
    class Coupons extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Coupons;

        }

    }
