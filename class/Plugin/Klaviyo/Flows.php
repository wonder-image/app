<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\FlowsApi
     */
    class Flows extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Flows;

        }

    }
