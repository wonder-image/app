<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\EventsApi
     */
    class Events extends Klaviyo {

        protected const EVENT_TYPE = 'event';
        protected const METRIC_TYPE = 'metric';
        protected const PROFILE_TYPE = 'profile';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Events;

        }

        public function all()
        {

            return $this->getEvents();

        }

        public function get($eventId)
        {

            return $this->getEvent($eventId);

        }

        public function create()
        {

            $this->eventType();

            return $this->createEvent();

        }

        public function properties(array $value): static
        {

            $this->eventType();

            return $this->addParams('data.attributes.properties', $value);

        }

        public function property(string $key, $value): static
        {

            $this->eventType();

            return $this->addParams("data.attributes.properties.$key", $value);

        }

        public function time($value): static
        {

            $this->eventType();

            return $this->dataAttribute('time', $value);

        }

        public function value($value): static
        {

            $this->eventType();

            return $this->dataAttribute('value', $value);

        }

        public function valueCurrency($value): static
        {

            $this->eventType();

            return $this->dataAttribute('value_currency', $value);

        }

        public function uniqueId($value): static
        {

            $this->eventType();

            return $this->dataAttribute('unique_id', $value);

        }

        public function metricName($value): static
        {

            $this->metricType();

            return $this->addParams('data.attributes.metric.data.attributes.name', $value);

        }

        public function metricService($value): static
        {

            $this->metricType();

            return $this->addParams('data.attributes.metric.data.attributes.service', $value);

        }

        public function profileId($value): static
        {

            $this->profileType();

            return $this->addParams('data.attributes.profile.data.id', $value);

        }

        public function email($value): static
        {

            return $this->profileAttribute('email', $value);

        }

        public function phoneNumber($value): static
        {

            if ($value !== null) {
                $value = preg_replace('/\s+/', '', (string) $value);
            }

            return $this->profileAttribute('phone_number', $value);

        }

        public function phone($value): static
        {

            return $this->phoneNumber($value);

        }

        public function externalId($value): static
        {

            return $this->profileAttribute('external_id', $value);

        }

        public function anonymousId($value): static
        {

            return $this->profileAttribute('anonymous_id', $value);

        }

        public function kx($value): static
        {

            return $this->profileAttribute('_kx', $value);

        }

        public function firstName($value): static
        {

            return $this->profileAttribute('first_name', $value);

        }

        public function lastName($value): static
        {

            return $this->profileAttribute('last_name', $value);

        }

        public function organization($value): static
        {

            return $this->profileAttribute('organization', $value);

        }

        public function locale($value): static
        {

            return $this->profileAttribute('locale', $value);

        }

        public function title($value): static
        {

            return $this->profileAttribute('title', $value);

        }

        public function image($value): static
        {

            return $this->profileAttribute('image', $value);

        }

        public function profileProperties(array $value): static
        {

            $this->profileType();

            return $this->addParams('data.attributes.profile.data.attributes.properties', $value);

        }

        public function profileProperty(string $key, $value): static
        {

            $this->profileType();

            return $this->addParams("data.attributes.profile.data.attributes.properties.$key", $value);

        }

        public function meta(array $value): static
        {

            $this->profileType();

            return $this->addParams('data.attributes.profile.data.attributes.meta', $value);

        }

        public function metaParam(string $key, $value): static
        {

            $this->profileType();

            return $this->addParams("data.attributes.profile.data.attributes.meta.$key", $value);

        }

        public function location(array $value): static
        {

            $this->profileType();

            return $this->addParams('data.attributes.profile.data.attributes.location', $value);

        }

        public function locationField(string $key, $value): static
        {

            $this->profileType();

            return $this->addParams("data.attributes.profile.data.attributes.location.$key", $value);

        }

        public function address1($value): static
        {

            return $this->locationField('address1', $value);

        }

        public function address2($value): static
        {

            return $this->locationField('address2', $value);

        }

        public function city($value): static
        {

            return $this->locationField('city', $value);

        }

        public function country($value): static
        {

            return $this->locationField('country', $value);

        }

        public function region($value): static
        {

            return $this->locationField('region', $value);

        }

        public function zip($value): static
        {

            return $this->locationField('zip', $value);

        }

        public function timezone($value): static
        {

            return $this->locationField('timezone', $value);

        }

        public function ip($value): static
        {

            return $this->locationField('ip', $value);

        }

        public function latitude($value): static
        {

            return $this->locationField('latitude', $value);

        }

        public function longitude($value): static
        {

            return $this->locationField('longitude', $value);

        }

        protected function eventType(): static
        {

            return $this->dataType(self::EVENT_TYPE);

        }

        protected function metricType(): static
        {

            $this->eventType();

            return $this->addParams('data.attributes.metric.data.type', self::METRIC_TYPE);

        }

        protected function profileType(): static
        {

            $this->eventType();

            return $this->addParams('data.attributes.profile.data.type', self::PROFILE_TYPE);

        }

        protected function profileAttribute(string $key, $value): static
        {

            $this->profileType();

            return $this->addParams("data.attributes.profile.data.attributes.$key", $value);

        }

    }
