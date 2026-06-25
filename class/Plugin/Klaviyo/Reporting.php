<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\ReportingApi
     */
    class Reporting extends Klaviyo {

        protected const CAMPAIGN_VALUES_REPORT_TYPE = 'campaign-values-report';
        protected const FLOW_SERIES_REPORT_TYPE = 'flow-series-report';
        protected const FLOW_VALUES_REPORT_TYPE = 'flow-values-report';
        protected const FORM_SERIES_REPORT_TYPE = 'form-series-report';
        protected const FORM_VALUES_REPORT_TYPE = 'form-values-report';
        protected const SEGMENT_SERIES_REPORT_TYPE = 'segment-series-report';
        protected const SEGMENT_VALUES_REPORT_TYPE = 'segment-values-report';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->Reporting;

        }

        public function campaignValues()
        {

            $this->reportType(self::CAMPAIGN_VALUES_REPORT_TYPE);

            return $this->queryCampaignValues();

        }

        public function campaignValuesRequest()
        {

            $this->reportType(self::CAMPAIGN_VALUES_REPORT_TYPE);

            return $this->queryCampaignValuesRequest();

        }

        public function flowSeries()
        {

            $this->reportType(self::FLOW_SERIES_REPORT_TYPE);

            return $this->queryFlowSeries();

        }

        public function flowSeriesRequest()
        {

            $this->reportType(self::FLOW_SERIES_REPORT_TYPE);

            return $this->queryFlowSeriesRequest();

        }

        public function flowValues()
        {

            $this->reportType(self::FLOW_VALUES_REPORT_TYPE);

            return $this->queryFlowValues();

        }

        public function flowValuesRequest()
        {

            $this->reportType(self::FLOW_VALUES_REPORT_TYPE);

            return $this->queryFlowValuesRequest();

        }

        public function formSeries()
        {

            $this->reportType(self::FORM_SERIES_REPORT_TYPE);

            return $this->queryFormSeries();

        }

        public function formSeriesRequest()
        {

            $this->reportType(self::FORM_SERIES_REPORT_TYPE);

            return $this->queryFormSeriesRequest();

        }

        public function formValues()
        {

            $this->reportType(self::FORM_VALUES_REPORT_TYPE);

            return $this->queryFormValues();

        }

        public function formValuesRequest()
        {

            $this->reportType(self::FORM_VALUES_REPORT_TYPE);

            return $this->queryFormValuesRequest();

        }

        public function segmentSeries()
        {

            $this->reportType(self::SEGMENT_SERIES_REPORT_TYPE);

            return $this->querySegmentSeries();

        }

        public function segmentSeriesRequest()
        {

            $this->reportType(self::SEGMENT_SERIES_REPORT_TYPE);

            return $this->querySegmentSeriesRequest();

        }

        public function segmentValues()
        {

            $this->reportType(self::SEGMENT_VALUES_REPORT_TYPE);

            return $this->querySegmentValues();

        }

        public function segmentValuesRequest()
        {

            $this->reportType(self::SEGMENT_VALUES_REPORT_TYPE);

            return $this->querySegmentValuesRequest();

        }

        public function statistics(array|string $value): static
        {

            if (is_array($value)) {
                return $this->addParams('data.attributes.statistics', $value);
            }

            return $this->pushParams('data.attributes.statistics', $value);

        }

        public function statistic(string $value): static
        {

            return $this->pushParams('data.attributes.statistics', $value);

        }

        public function groupBy(array|string $value): static
        {

            if (is_array($value)) {
                return $this->addParams('data.attributes.group_by', $value);
            }

            return $this->pushParams('data.attributes.group_by', $value);

        }

        public function group(string $value): static
        {

            return $this->pushParams('data.attributes.group_by', $value);

        }

        public function timeframe(array|string $value, $start = null, $end = null): static
        {

            if (is_array($value)) {
                return $this->addParams('data.attributes.timeframe', $value);
            }

            $this->addParams('data.attributes.timeframe.key', $value);
            $this->addParams('data.attributes.timeframe.start', $start);
            $this->addParams('data.attributes.timeframe.end', $end);

            return $this;

        }

        public function timeframeKey($value): static
        {

            return $this->addParams('data.attributes.timeframe.key', $value);

        }

        public function timeframeStart($value): static
        {

            return $this->addParams('data.attributes.timeframe.start', $value);

        }

        public function timeframeEnd($value): static
        {

            return $this->addParams('data.attributes.timeframe.end', $value);

        }

        public function interval($value): static
        {

            return $this->addParams('data.attributes.interval', $value);

        }

        public function conversionMetricId($value): static
        {

            return $this->addParams('data.attributes.conversion_metric_id', $value);

        }

        public function filter(string $value): static
        {

            return $this->addParams('data.attributes.filter', $value);

        }

        protected function reportType(string $type): static
        {

            return $this->dataType($type);

        }

    }
