<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\WebFeedsApi
     */
    class WebFeeds extends Klaviyo {

        protected const WEB_FEED_TYPE = 'web-feed';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->WebFeeds;

        }

        public function all()
        {

            return $this->getWebFeeds();

        }

        public function get($webFeedId)
        {

            return $this->getWebFeed($webFeedId);

        }

        public function create()
        {

            return $this->createWebFeed();

        }

        public function update($webFeedId)
        {

            return $this->updateWebFeed($webFeedId);

        }

        public function delete($webFeedId)
        {

            return $this->deleteWebFeed($webFeedId);

        }

        public function name($value): static
        {

            $this->dataType(self::WEB_FEED_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function url($value): static
        {

            $this->dataType(self::WEB_FEED_TYPE);

            return $this->dataAttribute('url', $value);

        }

        public function requestMethod($value): static
        {

            $this->dataType(self::WEB_FEED_TYPE);

            return $this->dataAttribute('request_method', $value);

        }

        public function contentTypeValue($value): static
        {

            $this->dataType(self::WEB_FEED_TYPE);

            return $this->dataAttribute('content_type', $value);

        }

    }
