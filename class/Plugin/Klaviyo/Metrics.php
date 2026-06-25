<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\MetricsApi
     */
    class Metrics extends Klaviyo {

        protected const CUSTOM_METRIC_TYPE = 'custom-metric';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Metrics;

        }

        public function all()
        {

            return $this->getMetrics();

        }

        public function get($metricId)
        {

            return $this->getMetric($metricId);

        }

        public function allCustom()
        {

            return $this->getCustomMetrics();

        }

        public function getCustom($metricId)
        {

            return $this->getCustomMetric($metricId);

        }

        public function create()
        {

            return $this->createCustomMetric();

        }

        public function update($metricId)
        {

            return $this->updateCustomMetric($metricId);

        }

        public function delete($metricId)
        {

            return $this->deleteCustomMetric($metricId);

        }

        public function name($value): static
        {

            $this->dataType(self::CUSTOM_METRIC_TYPE);

            return $this->dataAttribute('name', $value);

        }

        public function definition(array $value): static
        {

            $this->dataType(self::CUSTOM_METRIC_TYPE);

            return $this->dataAttribute('definition', $value);

        }

    }
