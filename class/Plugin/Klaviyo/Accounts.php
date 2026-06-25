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

        public function all()
        {

            return $this->getAccounts();

        }

        public function get($accountId)
        {

            return $this->getAccount($accountId);

        }

    }
