<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ProfilesApi
     */
    class Profiles extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Profiles;

        }

    }
