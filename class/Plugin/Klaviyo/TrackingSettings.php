<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\TrackingSettingsApi
     */
    class TrackingSettings extends Klaviyo {

        protected const TRACKING_SETTING_TYPE = 'tracking-setting';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->TrackingSettings;

        }

        public function all()
        {

            return $this->getTrackingSettings();

        }

        public function get($trackingSettingId)
        {

            return $this->getTrackingSetting($trackingSettingId);

        }

        public function update($trackingSettingId)
        {

            return $this->updateTrackingSetting($trackingSettingId);

        }

        public function autoAddParameters(bool $value = true): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('auto_add_parameters', $value);

        }

        public function utmSource(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('utm_source', $value);

        }

        public function utmMedium(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('utm_medium', $value);

        }

        public function utmCampaign(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('utm_campaign', $value);

        }

        public function utmId(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('utm_id', $value);

        }

        public function utmTerm(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('utm_term', $value);

        }

        public function customParameters(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->dataAttribute('custom_parameters', $value);

        }

        public function customParameter(array $value): static
        {

            $this->dataType(self::TRACKING_SETTING_TYPE);

            return $this->pushParams('data.attributes.custom_parameters', $value);

        }

    }
