<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\CustomObjectsApi
     */
    class CustomObjects extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->CustomObjects;

        }

    }
