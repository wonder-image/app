<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\SegmentsApi
     */
    class Segments extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Segments;

        }

    }
