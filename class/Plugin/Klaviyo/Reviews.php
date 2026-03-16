<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ReviewsApi
     */
    class Reviews extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Reviews;

        }

    }
