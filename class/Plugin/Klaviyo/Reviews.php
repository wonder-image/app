<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ReviewsApi
     */
    class Reviews extends Klaviyo {

        protected const REVIEW_TYPE = 'review';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Reviews;

        }

        public function all()
        {

            return $this->getReviews();

        }

        public function get($reviewId)
        {

            return $this->getReview($reviewId);

        }

        public function update($reviewId)
        {

            return $this->updateReview($reviewId);

        }

        public function status($value): static
        {

            $this->dataType(self::REVIEW_TYPE);

            return $this->dataAttribute('status', $value);

        }

    }
