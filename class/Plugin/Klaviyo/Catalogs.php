<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\CatalogsApi
     */
    class Catalogs extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Catalogs;

        }

    }
