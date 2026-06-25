<?php

    namespace Wonder\Plugin\Klaviyo;

    use InvalidArgumentException;

    /**
     * @mixin \KlaviyoAPI\API\ProfilesApi
     */
    class Profiles extends Klaviyo {

        protected const PROFILE_TYPE = 'profile';
        protected const LIST_TYPE = 'list';
        protected const PROFILE_SUBSCRIPTION_BULK_CREATE_JOB_TYPE = 'profile-subscription-bulk-create-job';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Profiles;

        }

        public function all()
        {

            return $this->object()->getProfiles(...$this->listQuery());

        }

        public function create()
        {

            return $this->createProfile();

        }

        public function createOrUpdate()
        {

            return $this->createOrUpdateProfile();

        }

        public function subscribe($subscriptionCreateJobCreateQuery = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $subscriptionCreateJobCreateQuery = is_array($subscriptionCreateJobCreateQuery) ? $subscriptionCreateJobCreateQuery : $this->subscriptionPayload();

            return $this->object()->bulkSubscribeProfiles(
                subscription_create_job_create_query: $subscriptionCreateJobCreateQuery,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function subscribeRequest($subscriptionCreateJobCreateQuery = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $subscriptionCreateJobCreateQuery = is_array($subscriptionCreateJobCreateQuery) ? $subscriptionCreateJobCreateQuery : $this->subscriptionPayload();

            return $this->object()->bulkSubscribeProfilesRequest(
                subscription_create_job_create_query: $subscriptionCreateJobCreateQuery,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function upsert()
        {

            return $this->createOrUpdate();

        }

        public function save()
        {

            return $this->createOrUpdate();

        }

        public function addToList($listId = null, $profileId = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            [$listId, $profileIds] = $this->resolveListMembership($listId, $profileId);

            return $this->Lists->addProfilesToList(
                id: $listId,
                list_members_add_query: [
                    'data' => array_map(
                        static fn ($id) => [
                            'type' => self::PROFILE_TYPE,
                            'id' => $id,
                        ],
                        $profileIds
                    ),
                ],
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function addToListRequest($listId = null, $profileId = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            [$listId, $profileIds] = $this->resolveListMembership($listId, $profileId);

            return $this->Lists->addProfilesToListRequest(
                id: $listId,
                list_members_add_query: [
                    'data' => array_map(
                        static fn ($id) => [
                            'type' => self::PROFILE_TYPE,
                            'id' => $id,
                        ],
                        $profileIds
                    ),
                ],
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function get($profileId)
        {

            return $this->object()->getProfile(...array_merge([
                'id' => $profileId
            ], $this->detailQuery()));

        }

        public function update($profileId = null)
        {

            $profileId = $profileId ?? ($this->params['data']['id'] ?? null);

            if (empty($profileId)) {
                throw new InvalidArgumentException('Missing profile id for update.');
            }

            return $this->updateProfile($profileId);

        }

        public function createProfile($profileCreateQuery = null, $additionalFieldsProfile = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $profileCreateQuery = is_array($profileCreateQuery) ? $profileCreateQuery : $this->createPayload();
            $additionalFieldsProfile = $additionalFieldsProfile ?? ($this->params['additional_fields_profile'] ?? null);

            return $this->object()->createProfile(
                profile_create_query: $profileCreateQuery,
                additional_fields_profile: $additionalFieldsProfile,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function createProfileRequest($profileCreateQuery = null, $additionalFieldsProfile = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $profileCreateQuery = is_array($profileCreateQuery) ? $profileCreateQuery : $this->createPayload();
            $additionalFieldsProfile = $additionalFieldsProfile ?? ($this->params['additional_fields_profile'] ?? null);

            return $this->object()->createProfileRequest(
                profile_create_query: $profileCreateQuery,
                additional_fields_profile: $additionalFieldsProfile,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function createOrUpdateProfile($profileUpsertQuery = null, $additionalFieldsProfile = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $profileUpsertQuery = is_array($profileUpsertQuery) ? $profileUpsertQuery : $this->upsertPayload();
            $additionalFieldsProfile = $additionalFieldsProfile ?? ($this->params['additional_fields_profile'] ?? null);

            return $this->object()->createOrUpdateProfile(
                profile_upsert_query: $profileUpsertQuery,
                additional_fields_profile: $additionalFieldsProfile,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function createOrUpdateProfileRequest($profileUpsertQuery = null, $additionalFieldsProfile = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $profileUpsertQuery = is_array($profileUpsertQuery) ? $profileUpsertQuery : $this->upsertPayload();
            $additionalFieldsProfile = $additionalFieldsProfile ?? ($this->params['additional_fields_profile'] ?? null);

            return $this->object()->createOrUpdateProfileRequest(
                profile_upsert_query: $profileUpsertQuery,
                additional_fields_profile: $additionalFieldsProfile,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function updateProfile($profileId, $profilePartialUpdateQuery = null, $additionalFieldsProfile = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $profilePartialUpdateQuery = is_array($profilePartialUpdateQuery) ? $profilePartialUpdateQuery : $this->updatePayload($profileId);
            $additionalFieldsProfile = $additionalFieldsProfile ?? ($this->params['additional_fields_profile'] ?? null);

            return $this->object()->updateProfile(
                id: $profileId,
                profile_partial_update_query: $profilePartialUpdateQuery,
                additional_fields_profile: $additionalFieldsProfile,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function updateProfileRequest($profileId, $profilePartialUpdateQuery = null, $additionalFieldsProfile = null, $apiKey = null, string $contentType = 'application/vnd.api+json')
        {

            $profilePartialUpdateQuery = is_array($profilePartialUpdateQuery) ? $profilePartialUpdateQuery : $this->updatePayload($profileId);
            $additionalFieldsProfile = $additionalFieldsProfile ?? ($this->params['additional_fields_profile'] ?? null);

            return $this->object()->updateProfileRequest(
                id: $profileId,
                profile_partial_update_query: $profilePartialUpdateQuery,
                additional_fields_profile: $additionalFieldsProfile,
                apiKey: $apiKey,
                contentType: $contentType
            );

        }

        public function id($value): static
        {

            $this->ensureProfileData();

            return $this->addParams('data.id', $value);

        }

        public function profileId($value): static
        {

            return $this->id($value);

        }

        public function withSubscriptions(): static
        {

            return $this->additionalField('profile', 'subscriptions');

        }

        public function attributes(array $value): static
        {

            $this->ensureProfileData();

            return $this->addParams('data.attributes', $value);

        }

        public function attribute(string $key, $value): static
        {

            $this->ensureProfileData();

            return $this->addParams("data.attributes.$key", $value);

        }

        public function clearAttribute(string $key): static
        {

            return $this->attribute($key, null);

        }

        public function email($value): static
        {

            return $this->attribute('email', $value);

        }

        public function phoneNumber($value): static
        {

            if ($value !== null) {
                $value = $this->normalizePhoneNumber($value);
            }

            return $this->attribute('phone_number', $value);

        }

        public function phone($value): static
        {

            return $this->phoneNumber($value);

        }

        public function listId($value): static
        {

            $this->ensureSubscriptionJob();

            return $this->addParams('subscription_job.data.relationships.list.data', [
                'type' => self::LIST_TYPE,
                'id' => $value,
            ]);

        }

        public function customSource($value): static
        {

            $this->ensureSubscriptionJob();

            return $this->addParams('subscription_job.data.attributes.custom_source', $value);

        }

        public function historicalImport(bool $value = true): static
        {

            $this->ensureSubscriptionJob();

            return $this->addParams('subscription_job.data.attributes.historical_import', $value);

        }

        public function emailMarketing($consentedAt = null): static
        {

            return $this->subscriptionConsent('email', 'marketing', 'SUBSCRIBED', $consentedAt);

        }

        public function smsMarketing($consentedAt = null): static
        {

            return $this->subscriptionConsent('sms', 'marketing', 'SUBSCRIBED', $consentedAt);

        }

        public function smsTransactional($consentedAt = null): static
        {

            return $this->subscriptionConsent('sms', 'transactional', 'SUBSCRIBED', $consentedAt);

        }

        public function ageGatedDateOfBirth($value): static
        {

            $this->ensureSubscriptionProfile();

            return $this->addParams(
                'subscription_job.data.attributes.profiles.data.0.attributes.age_gated_date_of_birth',
                $this->formatDateValue($value, 'Y-m-d')
            );

        }

        public function externalId($value): static
        {

            return $this->attribute('external_id', $value);

        }

        public function extId($value): static
        {

            return $this->externalId($value);

        }

        public function kx($value): static
        {

            return $this->attribute('_kx', $value);

        }

        public function firstName($value): static
        {

            return $this->attribute('first_name', $value);

        }

        public function lastName($value): static
        {

            return $this->attribute('last_name', $value);

        }

        public function organization($value): static
        {

            return $this->attribute('organization', $value);

        }

        public function locale($value): static
        {

            return $this->attribute('locale', $value);

        }

        public function title($value): static
        {

            return $this->attribute('title', $value);

        }

        public function image($value): static
        {

            return $this->attribute('image', $value);

        }

        public function properties(array $value): static
        {

            $this->ensureProfileData();

            return $this->addParams('data.attributes.properties', $value);

        }

        public function property(string $key, $value): static
        {

            $this->ensureProfileData();

            return $this->addParams("data.attributes.properties.$key", $value);

        }

        public function clearProperty(string $key): static
        {

            return $this->property($key, null);

        }

        public function location(array $value): static
        {

            $this->ensureProfileData();

            return $this->addParams('data.attributes.location', $value);

        }

        public function locationField(string $key, $value): static
        {

            $this->ensureProfileData();

            return $this->addParams("data.attributes.location.$key", $value);

        }

        public function clearLocationField(string $key): static
        {

            return $this->locationField($key, null);

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

        protected function ensureProfileData(): void
        {

            if (!isset($this->params['data']) || !is_array($this->params['data'])) {
                $this->params['data'] = [];
            }

            $this->params['data']['type'] = self::PROFILE_TYPE;

            if (!isset($this->params['data']['attributes']) || !is_array($this->params['data']['attributes'])) {
                $this->params['data']['attributes'] = [];
            }

        }

        protected function ensureSubscriptionJob(): void
        {

            if (!isset($this->params['subscription_job']) || !is_array($this->params['subscription_job'])) {
                $this->params['subscription_job'] = [];
            }

            if (!isset($this->params['subscription_job']['data']) || !is_array($this->params['subscription_job']['data'])) {
                $this->params['subscription_job']['data'] = [];
            }

            $this->params['subscription_job']['data']['type'] = self::PROFILE_SUBSCRIPTION_BULK_CREATE_JOB_TYPE;

            if (!isset($this->params['subscription_job']['data']['attributes']) || !is_array($this->params['subscription_job']['data']['attributes'])) {
                $this->params['subscription_job']['data']['attributes'] = [];
            }

        }

        protected function ensureSubscriptionProfile(): void
        {

            $this->ensureSubscriptionJob();

            if (!isset($this->params['subscription_job']['data']['attributes']['profiles']) || !is_array($this->params['subscription_job']['data']['attributes']['profiles'])) {
                $this->params['subscription_job']['data']['attributes']['profiles'] = [];
            }

            if (!isset($this->params['subscription_job']['data']['attributes']['profiles']['data']) || !is_array($this->params['subscription_job']['data']['attributes']['profiles']['data'])) {
                $this->params['subscription_job']['data']['attributes']['profiles']['data'] = [];
            }

            if (!isset($this->params['subscription_job']['data']['attributes']['profiles']['data'][0]) || !is_array($this->params['subscription_job']['data']['attributes']['profiles']['data'][0])) {
                $this->params['subscription_job']['data']['attributes']['profiles']['data'][0] = [];
            }

            $this->params['subscription_job']['data']['attributes']['profiles']['data'][0]['type'] = self::PROFILE_TYPE;

            if (!isset($this->params['subscription_job']['data']['attributes']['profiles']['data'][0]['attributes']) || !is_array($this->params['subscription_job']['data']['attributes']['profiles']['data'][0]['attributes'])) {
                $this->params['subscription_job']['data']['attributes']['profiles']['data'][0]['attributes'] = [];
            }

        }

        protected function listQuery(): array
        {

            return $this->onlyParams([
                'additional_fields_profile',
                'fields_profile',
                'filter',
                'include',
                'page_cursor',
                'page_size',
                'sort'
            ]);

        }

        protected function detailQuery(): array
        {

            return $this->onlyParams([
                'additional_fields_profile',
                'fields_list',
                'fields_profile',
                'fields_push_token',
                'fields_segment',
                'include'
            ]);

        }

        protected function createPayload(): array
        {

            return [
                'data' => $this->profileData()
            ];

        }

        protected function upsertPayload(): array
        {

            return [
                'data' => $this->profileData(includeKx: true)
            ];

        }

        protected function updatePayload($profileId): array
        {

            return [
                'data' => $this->profileData(id: $profileId)
            ];

        }

        protected function profileData(bool $includeKx = false, $id = null): array
        {

            $this->ensureProfileData();

            $data = $this->params['data'];
            $data['type'] = self::PROFILE_TYPE;

            if (!$includeKx && isset($data['attributes']['_kx'])) {
                unset($data['attributes']['_kx']);
            }

            if ($id !== null) {
                $data['id'] = $id;
            } elseif (isset($data['id'])) {
                unset($data['id']);
            }

            return $data;

        }

        protected function subscriptionPayload(): array
        {

            $this->ensureSubscriptionJob();

            $listId = $this->params['subscription_job']['data']['relationships']['list']['data']['id'] ?? null;

            if (empty($listId)) {
                throw new InvalidArgumentException('Missing list id for profile subscription.');
            }

            $data = $this->params['subscription_job']['data'];
            $data['type'] = self::PROFILE_SUBSCRIPTION_BULK_CREATE_JOB_TYPE;
            $data['attributes']['profiles']['data'] = [$this->subscriptionProfileData()];
            $data['relationships']['list']['data'] = [
                'type' => self::LIST_TYPE,
                'id' => $listId,
            ];

            return [
                'data' => $data
            ];

        }

        protected function subscriptionProfileData(): array
        {

            $this->ensureSubscriptionProfile();

            $profile = $this->params['subscription_job']['data']['attributes']['profiles']['data'][0];
            $profile['type'] = self::PROFILE_TYPE;
            $profile['attributes'] ??= [];

            $sourceAttributes = $this->params['data']['attributes'] ?? [];

            if (!array_key_exists('email', $profile['attributes']) && array_key_exists('email', $sourceAttributes)) {
                $profile['attributes']['email'] = $sourceAttributes['email'];
            }

            if (!array_key_exists('phone_number', $profile['attributes']) && array_key_exists('phone_number', $sourceAttributes)) {
                $profile['attributes']['phone_number'] = $sourceAttributes['phone_number'];
            }

            $email = $profile['attributes']['email'] ?? null;
            $phoneNumber = $profile['attributes']['phone_number'] ?? null;

            if (empty($email) && empty($phoneNumber)) {
                throw new InvalidArgumentException('Missing email or phone number for profile subscription.');
            }

            if (empty($profile['attributes']['subscriptions']) || !is_array($profile['attributes']['subscriptions'])) {
                throw new InvalidArgumentException('Missing marketing consent for profile subscription.');
            }

            $this->validateSubscriptionProfile($profile);

            return $profile;

        }

        protected function subscriptionConsent(string $channel, string $scope, $consent = 'SUBSCRIBED', $consentedAt = null): static
        {

            $this->ensureSubscriptionProfile();

            $consent = strtoupper((string) $consent);

            $this->addParams(
                "subscription_job.data.attributes.profiles.data.0.attributes.subscriptions.$channel.$scope.consent",
                $consent
            );

            if ($consentedAt !== null) {
                $this->addParams(
                    "subscription_job.data.attributes.profiles.data.0.attributes.subscriptions.$channel.$scope.consented_at",
                    $this->formatDateValue($consentedAt)
                );
            }

            return $this;

        }

        protected function resolveListMembership($listId = null, $profileId = null): array
        {

            $listId = $listId ?? ($this->params['subscription_job']['data']['relationships']['list']['data']['id'] ?? null);

            if (empty($listId)) {
                throw new InvalidArgumentException('Missing list id for addToList.');
            }

            if (is_array($profileId)) {
                $profileIds = $profileId;
            } else {
                $profileIds = [$profileId ?? ($this->params['data']['id'] ?? null)];
            }

            $profileIds = array_values(array_filter($profileIds, static fn ($id) => !empty($id)));

            if ($profileIds === []) {
                throw new InvalidArgumentException('Missing profile id for addToList.');
            }

            return [$listId, $profileIds];

        }

        protected function formatDateValue($value, string $format = \DateTimeInterface::ATOM): string
        {

            if ($value instanceof \DateTimeInterface) {
                return $value->format($format);
            }

            return (string) $value;

        }

        protected function validateSubscriptionProfile(array $profile): void
        {

            $attributes = $profile['attributes'] ?? [];
            $subscriptions = $attributes['subscriptions'] ?? [];
            $historicalImport = (bool) ($this->params['subscription_job']['data']['attributes']['historical_import'] ?? false);

            if (isset($subscriptions['email']['marketing'])) {
                $email = $attributes['email'] ?? null;

                if (!$this->isValidEmail($email)) {
                    throw new InvalidArgumentException('Email marketing subscription requires a valid email address.');
                }

                if ($historicalImport && empty($subscriptions['email']['marketing']['consented_at'])) {
                    throw new InvalidArgumentException('Historical imports require consented_at for email marketing subscription.');
                }
            }

            if (isset($subscriptions['sms']['marketing']) || isset($subscriptions['sms']['transactional'])) {
                $phoneNumber = $attributes['phone_number'] ?? null;

                if (!$this->isValidE164PhoneNumber($phoneNumber)) {
                    throw new InvalidArgumentException('SMS subscription requires a phone number in E.164 format, for example +393331234567.');
                }

                if ($historicalImport && isset($subscriptions['sms']['marketing']) && empty($subscriptions['sms']['marketing']['consented_at'])) {
                    throw new InvalidArgumentException('Historical imports require consented_at for SMS marketing subscription.');
                }

                if ($historicalImport && isset($subscriptions['sms']['transactional']) && empty($subscriptions['sms']['transactional']['consented_at'])) {
                    throw new InvalidArgumentException('Historical imports require consented_at for SMS transactional subscription.');
                }
            }

        }

        protected function normalizePhoneNumber($value): string
        {

            $value = trim((string) $value);

            if ($value === '') {
                return '';
            }

            $hasPlusPrefix = str_starts_with($value, '+');
            $digits = preg_replace('/\D+/', '', $value) ?? '';

            return ($hasPlusPrefix ? '+' : '').$digits;

        }

        protected function isValidEmail($value): bool
        {

            return is_string($value) && filter_var($value, FILTER_VALIDATE_EMAIL) !== false;

        }

        protected function isValidE164PhoneNumber($value): bool
        {

            return is_string($value) && preg_match('/^\+[1-9]\d{7,14}$/', $value) === 1;

        }

        protected function onlyParams(array $keys): array
        {

            $params = [];

            foreach ($keys as $key) {

                if (!array_key_exists($key, $this->params)) {
                    continue;
                }

                $params[$key] = $this->params[$key];

            }

            return $params;

        }

    }
