<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\DataPrivacyApi
     */
    class DataPrivacy extends Klaviyo {

        protected const DELETION_JOB_TYPE = 'data-privacy-deletion-job';
        protected const PROFILE_TYPE = 'profile';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->DataPrivacy;

        }

        public function create()
        {

            $this->jobType();

            return $this->requestProfileDeletion();

        }

        public function requestDeletion()
        {

            return $this->create();

        }

        public function profileId($value): static
        {

            $this->jobType();
            $this->profileType();

            return $this->addParams('data.attributes.profile.data.id', $value);

        }

        public function email($value): static
        {

            $this->jobType();
            $this->profileType();

            return $this->addParams('data.attributes.profile.data.attributes.email', $value);

        }

        public function phoneNumber($value): static
        {

            $this->jobType();
            $this->profileType();

            if ($value !== null) {
                $value = preg_replace('/\s+/', '', (string) $value);
            }

            return $this->addParams('data.attributes.profile.data.attributes.phone_number', $value);

        }

        public function phone($value): static
        {

            return $this->phoneNumber($value);

        }

        protected function jobType(): static
        {

            return $this->dataType(self::DELETION_JOB_TYPE);

        }

        protected function profileType(): static
        {

            return $this->addParams('data.attributes.profile.data.type', self::PROFILE_TYPE);

        }

    }
