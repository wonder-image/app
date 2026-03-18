<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\TagsApi
     */
    class Tags extends Klaviyo {

        protected const TAG_TYPE = 'tag';
        protected const TAG_GROUP_TYPE = 'tag-group';
        protected const CAMPAIGN_TYPE = 'campaign';
        protected const FLOW_TYPE = 'flow';
        protected const LIST_TYPE = 'list';
        protected const SEGMENT_TYPE = 'segment';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Tags;

        }

        public function all(?string $filter = null)
        {

            if ($filter !== null) {
                $this->addParams('filter', $filter);
            }

            return $this->getTags();

        }

        public function get($tagId)
        {

            return $this->getTag($tagId);

        }

        public function create()
        {

            $this->tagType();

            return $this->createTag();

        }

        public function update($tagId)
        {

            $this->tagType();

            return $this->updateTag($tagId);

        }

        public function delete($tagId)
        {

            return $this->deleteTag($tagId);

        }

        public function allGroups(?string $filter = null)
        {

            if ($filter !== null) {
                $this->addParams('filter', $filter);
            }

            return $this->getTagGroups();

        }

        public function group($groupId)
        {

            return $this->getTagGroup($groupId);

        }

        public function createGroup()
        {

            $this->tagGroupType();

            return $this->createTagGroup();

        }

        public function updateGroup($groupId)
        {

            $this->tagGroupType();

            return $this->updateTagGroup($groupId);

        }

        public function deleteGroup($groupId)
        {

            return $this->deleteTagGroup($groupId);

        }

        public function addCampaigns($tagId, ?array $campaignIds = null)
        {

            if ($campaignIds !== null) {
                $this->campaignIds($campaignIds);
            }

            return $this->tagCampaigns($tagId);

        }

        public function removeCampaigns($tagId, ?array $campaignIds = null)
        {

            if ($campaignIds !== null) {
                $this->campaignIds($campaignIds);
            }

            return $this->removeTagFromCampaigns($tagId);

        }

        public function addFlows($tagId, ?array $flowIds = null)
        {

            if ($flowIds !== null) {
                $this->flowIds($flowIds);
            }

            return $this->tagFlows($tagId);

        }

        public function removeFlows($tagId, ?array $flowIds = null)
        {

            if ($flowIds !== null) {
                $this->flowIds($flowIds);
            }

            return $this->removeTagFromFlows($tagId);

        }

        public function addLists($tagId, ?array $listIds = null)
        {

            if ($listIds !== null) {
                $this->listIds($listIds);
            }

            return $this->tagLists($tagId);

        }

        public function removeLists($tagId, ?array $listIds = null)
        {

            if ($listIds !== null) {
                $this->listIds($listIds);
            }

            return $this->removeTagFromLists($tagId);

        }

        public function addSegments($tagId, ?array $segmentIds = null)
        {

            if ($segmentIds !== null) {
                $this->segmentIds($segmentIds);
            }

            return $this->tagSegments($tagId);

        }

        public function removeSegments($tagId, ?array $segmentIds = null)
        {

            if ($segmentIds !== null) {
                $this->segmentIds($segmentIds);
            }

            return $this->removeTagFromSegments($tagId);

        }

        public function name($value): static
        {

            return $this->dataAttribute('name', $value);

        }

        public function exclusive(bool $value = true): static
        {

            $this->tagGroupType();

            return $this->dataAttribute('exclusive', $value);

        }

        public function returnFields(array $value): static
        {

            $this->tagGroupType();

            return $this->dataAttribute('return_fields', $value);

        }

        public function returnField($value): static
        {

            $this->tagGroupType();

            return $this->pushParams('data.attributes.return_fields', $value);

        }

        public function tagGroupId($value): static
        {

            $this->tagType();

            return $this->relationshipId('tag_group', self::TAG_GROUP_TYPE, $value);

        }

        public function campaignIds(array $values): static
        {

            return $this->relationshipData(self::CAMPAIGN_TYPE, $values);

        }

        public function flowIds(array $values): static
        {

            return $this->relationshipData(self::FLOW_TYPE, $values);

        }

        public function listIds(array $values): static
        {

            return $this->relationshipData(self::LIST_TYPE, $values);

        }

        public function segmentIds(array $values): static
        {

            return $this->relationshipData(self::SEGMENT_TYPE, $values);

        }

        protected function tagType(): static
        {

            return $this->dataType(self::TAG_TYPE);

        }

        protected function tagGroupType(): static
        {

            return $this->dataType(self::TAG_GROUP_TYPE);

        }

        protected function relationshipData(string $type, array $values): static
        {

            return $this->addParams('data', array_map(
                static fn ($id) => [
                    'type' => $type,
                    'id' => $id,
                ],
                $values
            ));

        }

    }
