<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\EventsApi
     */
    class Events extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Events;

        }

    }
