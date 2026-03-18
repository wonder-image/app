<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ListsApi
     */
    class Lists extends Klaviyo {

        protected const LIST_TYPE = 'list';
        protected const PROFILE_TYPE = 'profile';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Lists;

        }

        public function all()
        {

            return $this->getLists();

        }

        public function get($listId)
        {

            return $this->getList($listId);

        }

        public function create()
        {

            return $this->createList();

        }

        public function update($listId)
        {

            return $this->updateList($listId);

        }

        public function delete($listId)
        {

            return $this->deleteList($listId);

        }

        public function addProfile($listId, $profileId)
        {

            return $this->addProfiles($listId, [$profileId]);

        }

        public function addProfiles($listId, ?array $profileIds = null)
        {

            if ($profileIds !== null) {
                $this->profileIds($profileIds);
            }

            return $this->addProfilesToList($listId);

        }

        public function removeProfile($listId, $profileId)
        {

            return $this->removeProfiles($listId, [$profileId]);

        }

        public function removeProfiles($listId, ?array $profileIds = null)
        {

            if ($profileIds !== null) {
                $this->profileIds($profileIds);
            }

            return $this->removeProfilesFromList($listId);

        }

        public function name($value): static
        {

            $this->dataType(self::LIST_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function optInProcess($value): static
        {

            $this->dataType(self::LIST_TYPE);

            return $this->dataAttribute('opt_in_process', $value);

        }

        public function profileId($value): static
        {

            return $this->pushParams('data', [
                'type' => self::PROFILE_TYPE,
                'id' => $value,
            ]);

        }

        public function profileIds(array $values): static
        {

            return $this->addParams('data', array_map(
                static fn ($id) => [
                    'type' => self::PROFILE_TYPE,
                    'id' => $id,
                ],
                $values
            ));

        }

    }
