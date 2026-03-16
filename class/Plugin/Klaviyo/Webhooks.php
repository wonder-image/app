<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\WebhooksApi
     */
    class Webhooks extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Webhooks;

        }

    }
