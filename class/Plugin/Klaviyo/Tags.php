<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\TagsApi
     */
    class Tags extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Tags;

        }

    }
