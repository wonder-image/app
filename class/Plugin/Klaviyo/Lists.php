<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ListsApi
     */
    class Lists extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Lists;

        }

    }
