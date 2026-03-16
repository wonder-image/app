<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\AccountsApi
     */
    class Accounts extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Accounts;

        }

    }
