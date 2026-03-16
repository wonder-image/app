<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\WebFeedsApi
     */
    class WebFeeds extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->WebFeeds;

        }

    }
