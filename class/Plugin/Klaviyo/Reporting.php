<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ReportingApi
     */
    class Reporting extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Reporting;

        }

    }
