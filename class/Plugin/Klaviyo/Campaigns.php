<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\CampaignsApi
     */
    class Campaigns extends Klaviyo {

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Campaigns;

        }

    }
