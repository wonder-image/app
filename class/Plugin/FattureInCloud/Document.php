<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\IssuedDocument as FattureInCloudDocument;
    use FattureInCloud\Model\{
        CreateIssuedDocumentRequest,
        CreateIssuedDocumentResponse,
        ModifyIssuedDocumentRequest,
        ModifyIssuedDocumentResponse,
        GetIssuedDocumentResponse,
        ListIssuedDocumentsResponse,
        Entity,
        IssuedDocumentOptions
    };

    class Document extends FattureInCloudDocument
    {
        
        use HandlesApiErrors;

        public function create(): CreateIssuedDocumentResponse
        {

            return $this->guard('document.create', function () {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedDocuments();
                $request = (new CreateIssuedDocumentRequest())
                    ->setData($this)
                    ->setOptions($this->options());

                return $instance->createIssuedDocument($connect::$companyId, $request);
            });

        }

        public function get(int $documentId, ?string $fields = null, ?string $fieldset = null): GetIssuedDocumentResponse
        {

            return $this->guard('document.get', function () use ($documentId, $fields, $fieldset) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedDocuments();

                return $instance->getIssuedDocument($connect::$companyId, $documentId, $fields, $fieldset);
            });

        }

        public function list(
            string $type = 'invoice',
            ?string $fields = null,
            ?string $fieldset = null,
            ?string $sort = null,
            int $page = 1,
            int $perPage = 25,
            ?string $q = null,
            ?string $inclusive = null
        ): ListIssuedDocumentsResponse
        {

            return $this->guard('document.list', function () use ($type, $fields, $fieldset, $sort, $page, $perPage, $q, $inclusive) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedDocuments();

                return $instance->listIssuedDocuments(
                    $connect::$companyId,
                    $type,
                    $fields,
                    $fieldset,
                    $sort,
                    $page,
                    $perPage,
                    $q,
                    $inclusive
                );
            });

        }

        public function update(int $documentId): ModifyIssuedDocumentResponse
        {

            return $this->guard('document.update', function () use ($documentId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedDocuments();
                $request = (new ModifyIssuedDocumentRequest())
                    ->setData($this)
                    ->setOptions($this->options());

                return $instance->modifyIssuedDocument($connect::$companyId, $documentId, $request);
            });

        }

        public function delete(int $documentId): void
        {

            $this->guard('document.delete', function () use ($documentId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedDocuments();

                $instance->deleteIssuedDocument($connect::$companyId, $documentId);
            });

        }

        private function options(): IssuedDocumentOptions
        {

            return (new IssuedDocumentOptions())
                ->setFixPayments(true);

        }

    }
