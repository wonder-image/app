<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\DataPrivacyApi
     */
    class DataPrivacy extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->DataPrivacy;

        }

    }
