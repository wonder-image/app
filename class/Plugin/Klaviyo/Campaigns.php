<?php

    namespace Wonder\Plugin\Klaviyo;

    use InvalidArgumentException;

    /**
     * @mixin \KlaviyoAPI\API\CampaignsApi
     */
    class Campaigns extends Klaviyo {

        protected const CAMPAIGN_TYPE = 'campaign';
        protected const CAMPAIGN_MESSAGE_TYPE = 'campaign-message';
        protected const TEMPLATE_TYPE = 'template';
        protected const CAMPAIGN_SEND_JOB_TYPE = 'campaign-send-job';
        protected const CAMPAIGN_RECIPIENT_ESTIMATION_JOB_TYPE = 'campaign-recipient-estimation-job';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Campaigns;

        }

        public function all(?string $filter = null)
        {

            $filter ??= $this->params['filter'] ?? 'equals(archived,false)';

            return $this->object()->getCampaigns(...array_merge([
                'filter' => $filter
            ], $this->onlyParams([
                'fields_campaign_message',
                'fields_campaign',
                'fields_tag',
                'include',
                'page_cursor',
                'sort',
                'apiKey',
                'contentType',
            ])));

        }

        public function get($campaignId)
        {

            return $this->object()->getCampaign(...array_merge([
                'id' => $campaignId
            ], $this->onlyParams([
                'fields_campaign_message',
                'fields_campaign',
                'fields_tag',
                'include',
                'apiKey',
                'contentType',
            ])));

        }

        public function message($messageId)
        {

            return $this->object()->getCampaignMessage(...array_merge([
                'id' => $messageId
            ], $this->onlyParams([
                'fields_campaign_message',
                'fields_campaign',
                'fields_image',
                'fields_template',
                'include',
                'apiKey',
                'contentType',
            ])));

        }

        public function sendJob($jobId)
        {

            return $this->object()->getCampaignSendJob(...array_merge([
                'id' => $jobId
            ], $this->onlyParams([
                'fields_campaign_send_job',
                'apiKey',
                'contentType',
            ])));

        }

        public function recipientEstimation($campaignId)
        {

            return $this->object()->getCampaignRecipientEstimation(...array_merge([
                'id' => $campaignId
            ], $this->onlyParams([
                'fields_campaign_recipient_estimation',
                'apiKey',
                'contentType',
            ])));

        }

        public function recipientEstimationJob($jobId)
        {

            return $this->object()->getCampaignRecipientEstimationJob(...array_merge([
                'id' => $jobId
            ], $this->onlyParams([
                'fields_campaign_recipient_estimation_job',
                'apiKey',
                'contentType',
            ])));

        }

        public function create()
        {

            $this->campaignType();

            return $this->createCampaign();

        }

        public function update($campaignId)
        {

            $this->campaignType();

            return $this->updateCampaign($campaignId);

        }

        public function delete($campaignId)
        {

            return $this->deleteCampaign($campaignId);

        }

        public function duplicate($campaignId, $newName = null)
        {

            if ($newName !== null) {
                $this->cloneName($newName);
            }

            $this->campaignType();
            $this->dataId($campaignId);

            return $this->createCampaignClone();

        }

        public function assignTemplate($campaignMessageId, $templateId = null)
        {

            if ($templateId !== null) {
                $this->templateId($templateId);
            }

            $this->messageType();
            $this->dataId($campaignMessageId);

            return $this->assignTemplateToCampaignMessage();

        }

        public function updateMessage($messageId)
        {

            $this->messageType();

            return $this->updateCampaignMessage($messageId);

        }

        public function send($campaignId = null)
        {

            $campaignId ??= $this->params['data']['id'] ?? null;

            if (empty($campaignId)) {
                throw new InvalidArgumentException('Missing campaign id for send.');
            }

            $this->dataType(self::CAMPAIGN_SEND_JOB_TYPE);
            $this->dataId($campaignId);

            return $this->sendCampaign();

        }

        public function cancelSend($jobId, ?string $action = 'cancel')
        {

            if ($action !== null) {
                $this->action($action);
            }

            $this->dataType(self::CAMPAIGN_SEND_JOB_TYPE);

            return $this->cancelCampaignSend($jobId);

        }

        public function refreshRecipientEstimation($campaignId = null)
        {

            $campaignId ??= $this->params['data']['id'] ?? null;

            if (empty($campaignId)) {
                throw new InvalidArgumentException('Missing campaign id for recipient estimation.');
            }

            $this->dataType(self::CAMPAIGN_RECIPIENT_ESTIMATION_JOB_TYPE);
            $this->dataId($campaignId);

            return $this->refreshCampaignRecipientEstimation();

        }

        public function campaignId($value): static
        {

            $this->campaignType();

            return $this->dataId($value);

        }

        public function messageId($value): static
        {

            $this->messageType();

            return $this->dataId($value);

        }

        public function name($value): static
        {

            $this->campaignType();

            return $this->dataAttribute('name', $value);

        }

        public function audiences(array $value): static
        {

            $this->campaignType();

            return $this->addParams('data.attributes.audiences', $value);

        }

        public function audience(string $key, $value): static
        {

            $this->campaignType();

            return $this->addParams("data.attributes.audiences.$key", $value);

        }

        public function sendStrategy(array $value): static
        {

            $this->campaignType();

            return $this->addParams('data.attributes.send_strategy', $value);

        }

        public function sendStrategyParam(string $key, $value): static
        {

            $this->campaignType();

            return $this->addParams("data.attributes.send_strategy.$key", $value);

        }

        public function sendOptions(array $value): static
        {

            $this->campaignType();

            return $this->addParams('data.attributes.send_options', $value);

        }

        public function sendOption(string $key, $value): static
        {

            $this->campaignType();

            return $this->addParams("data.attributes.send_options.$key", $value);

        }

        public function trackingOptions(array $value): static
        {

            $this->campaignType();

            return $this->addParams('data.attributes.tracking_options', $value);

        }

        public function trackingOption(string $key, $value): static
        {

            $this->campaignType();

            return $this->addParams("data.attributes.tracking_options.$key", $value);

        }

        public function campaignMessages(array $value): static
        {

            $this->campaignType();

            return $this->addParams('data.attributes.campaign_messages', $value);

        }

        public function cloneName($value): static
        {

            $this->campaignType();

            return $this->addParams('data.attributes.new_name', $value);

        }

        public function action($value): static
        {

            return $this->addParams('data.attributes.action', $value);

        }

        public function templateId($value): static
        {

            return $this->relationshipId('template', self::TEMPLATE_TYPE, $value);

        }

        protected function campaignType(): static
        {

            return $this->dataType(self::CAMPAIGN_TYPE);

        }

        protected function messageType(): static
        {

            return $this->dataType(self::CAMPAIGN_MESSAGE_TYPE);

        }

    }
