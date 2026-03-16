<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\TemplatesApi
     */
    class Templates extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Templates;

        }

    }
