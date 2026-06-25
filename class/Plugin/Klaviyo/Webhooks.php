<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\WebhooksApi
     */
    class Webhooks extends Klaviyo {

        protected const WEBHOOK_TYPE = 'webhook';
        protected const WEBHOOK_TOPIC_TYPE = 'webhook-topic';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Webhooks;

        }

        public function all()
        {

            return $this->getWebhooks();

        }

        public function get($webhookId)
        {

            return $this->getWebhook($webhookId);

        }

        public function topics()
        {

            return $this->getWebhookTopics();

        }

        public function topic($topicId)
        {

            return $this->getWebhookTopic($topicId);

        }

        public function create()
        {

            return $this->createWebhook();

        }

        public function update($webhookId)
        {

            return $this->updateWebhook($webhookId);

        }

        public function delete($webhookId)
        {

            return $this->deleteWebhook($webhookId);

        }

        public function name($value): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function description($value): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->dataAttribute('description', $value);

        }

        public function endpointUrl($value): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->dataAttribute('endpoint_url', $value);

        }

        public function secretKey($value): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->dataAttribute('secret_key', $value);

        }

        public function enabled(bool $value = true): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->dataAttribute('enabled', $value);

        }

        public function topicId($value): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->pushParams('data.relationships.webhook_topics.data', [
                'type' => self::WEBHOOK_TOPIC_TYPE,
                'id' => $value,
            ]);

        }

        public function topicIds(array $values): static
        {

            $this->dataType(self::WEBHOOK_TYPE);

            return $this->addParams('data.relationships.webhook_topics.data', array_map(
                static fn ($id) => [
                    'type' => self::WEBHOOK_TOPIC_TYPE,
                    'id' => $id,
                ],
                $values
            ));

        }

    }
