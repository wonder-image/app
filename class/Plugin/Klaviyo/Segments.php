<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\SegmentsApi
     */
    class Segments extends Klaviyo {

        protected const SEGMENT_TYPE = 'segment';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Segments;

        }

        public function all()
        {

            return $this->getSegments();

        }

        public function get($segmentId)
        {

            return $this->getSegment($segmentId);

        }

        public function create()
        {

            return $this->createSegment();

        }

        public function update($segmentId)
        {

            return $this->updateSegment($segmentId);

        }

        public function delete($segmentId)
        {

            return $this->deleteSegment($segmentId);

        }

        public function name($value): static
        {

            $this->dataType(self::SEGMENT_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function definition(array $value): static
        {

            $this->dataType(self::SEGMENT_TYPE);

            return $this->dataAttribute('definition', $value);

        }

        public function starred(bool $value = true): static
        {

            $this->dataType(self::SEGMENT_TYPE);

            return $this->dataAttribute('is_starred', $value);

        }

    }
