<?php

    namespace Wonder\Plugin\Klaviyo;

    /**
     * @mixin \KlaviyoAPI\API\CustomObjectsApi
     */
    class CustomObjects extends Klaviyo {

        protected const DATA_SOURCE_TYPE = 'data-source';
        protected const DATA_SOURCE_RECORD_TYPE = 'data-source-record';
        protected const DATA_SOURCE_RECORD_CREATE_JOB_TYPE = 'data-source-record-create-job';
        protected const DATA_SOURCE_RECORD_BULK_CREATE_JOB_TYPE = 'data-source-record-bulk-create-job';

        public function object(): \KlaviyoAPI\Subclient
        {

            return $this->CustomObjects;

        }

        public function all()
        {

            return $this->getDataSources();

        }

        public function get($dataSourceId)
        {

            return $this->getDataSource($dataSourceId);

        }

        public function create()
        {

            $this->dataSourceType();

            return $this->createDataSource();

        }

        public function delete($dataSourceId)
        {

            return $this->deleteDataSource($dataSourceId);

        }

        public function createRecord($dataSourceId = null, ?array $record = null)
        {

            if ($dataSourceId !== null) {
                $this->dataSourceId($dataSourceId);
            }

            if ($record !== null) {
                $this->record($record);
            }

            $this->dataType(self::DATA_SOURCE_RECORD_CREATE_JOB_TYPE);
            $this->addParams('data.attributes.data_source_record.data.type', self::DATA_SOURCE_RECORD_TYPE);

            return $this->createDataSourceRecord();

        }

        public function createRecordRequest($dataSourceId = null, ?array $record = null)
        {

            if ($dataSourceId !== null) {
                $this->dataSourceId($dataSourceId);
            }

            if ($record !== null) {
                $this->record($record);
            }

            $this->dataType(self::DATA_SOURCE_RECORD_CREATE_JOB_TYPE);
            $this->addParams('data.attributes.data_source_record.data.type', self::DATA_SOURCE_RECORD_TYPE);

            return $this->createDataSourceRecordRequest();

        }

        public function bulkCreateRecords($dataSourceId = null, ?array $records = null)
        {

            if ($dataSourceId !== null) {
                $this->dataSourceId($dataSourceId);
            }

            if ($records !== null) {
                $this->records($records);
            }

            $this->dataType(self::DATA_SOURCE_RECORD_BULK_CREATE_JOB_TYPE);

            return $this->bulkCreateDataSourceRecords();

        }

        public function bulkCreateRecordsRequest($dataSourceId = null, ?array $records = null)
        {

            if ($dataSourceId !== null) {
                $this->dataSourceId($dataSourceId);
            }

            if ($records !== null) {
                $this->records($records);
            }

            $this->dataType(self::DATA_SOURCE_RECORD_BULK_CREATE_JOB_TYPE);

            return $this->bulkCreateDataSourceRecordsRequest();

        }

        public function title($value): static
        {

            $this->dataSourceType();

            return $this->dataAttribute('title', $value);

        }

        public function visibility($value): static
        {

            $this->dataSourceType();

            return $this->dataAttribute('visibility', $value);

        }

        public function description($value): static
        {

            $this->dataSourceType();

            return $this->dataAttribute('description', $value);

        }

        public function dataSourceId($value): static
        {

            return $this->relationshipId('data_source', self::DATA_SOURCE_TYPE, $value);

        }

        public function record(array $value): static
        {

            return $this->addParams('data.attributes.data_source_record.data', [
                'type' => self::DATA_SOURCE_RECORD_TYPE,
                'attributes' => [
                    'record' => $value,
                ],
            ]);

        }

        public function recordField(string $key, $value): static
        {

            return $this->addParams("data.attributes.data_source_record.data.attributes.record.$key", $value);

        }

        public function records(array $values): static
        {

            return $this->addParams('data.attributes.data_source_records.data', array_map(
                static fn (array $record) => [
                    'type' => self::DATA_SOURCE_RECORD_TYPE,
                    'attributes' => [
                        'record' => $record,
                    ],
                ],
                $values
            ));

        }

        protected function dataSourceType(): static
        {

            return $this->dataType(self::DATA_SOURCE_TYPE);

        }

    }
