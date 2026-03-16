<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\TrackingSettingsApi
     */
    class TrackingSettings extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->TrackingSettings;

        }

    }
