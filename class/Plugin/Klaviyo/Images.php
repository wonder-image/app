<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ImagesApi
     */
    class Images extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Images;

        }

    }
