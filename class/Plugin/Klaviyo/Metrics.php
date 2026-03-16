<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\MetricsApi
     */
    class Metrics extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Metrics;

        }

    }
