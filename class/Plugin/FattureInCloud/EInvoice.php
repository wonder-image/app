<?php

    namespace Wonder\Plugin\FattureInCloud;

    use Wonder\Plugin\FattureInCloud\Api as FattureInCloudApi;
    use FattureInCloud\Model\{
        IssuedDocumentEiData,
        SendEInvoiceRequestData,
        VerifyEInvoiceXmlResponse,
        VerifyEInvoiceXmlErrorResponse,
        GetEInvoiceRejectionReasonResponse,
        SendEInvoiceRequest,
        SendEInvoiceResponse,
        SendEInvoiceRequestOptions
    };

    class EInvoice extends IssuedDocumentEiData
    {

        use HandlesApiErrors;

        public function verifyXml(int $documentId): VerifyEInvoiceXmlResponse|VerifyEInvoiceXmlErrorResponse
        {

            return $this->guard('e_invoice.verify_xml', function () use ($documentId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedEInvoices();

                return $instance->verifyEInvoiceXml($connect::$companyId, $documentId);
            });

        }

        public function rejectionReason(int $documentId): GetEInvoiceRejectionReasonResponse
        {

            return $this->guard('e_invoice.rejection_reason', function () use ($documentId) {
                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedEInvoices();

                return $instance->getEInvoiceRejectionReason($connect::$companyId, $documentId);
            });

        }

        public function send(int $documentId, bool $test = false): SendEInvoiceResponse
        {

            return $this->guard('e_invoice.send', function () use ($documentId, $test) {

                $connect = FattureInCloudApi::connect();
                $instance = $connect->issuedEInvoices();

                # I dati del send endpoint sono distinti da ei_data documento.
                $requestData = new SendEInvoiceRequestData();
                $request = (new SendEInvoiceRequest())->setData($requestData);

                $sendOptions = new SendEInvoiceRequestOptions([ 'dry_run' => $test ]);
                $request->setOptions($sendOptions);

                return $instance->sendEInvoice($connect::$companyId, $documentId, $request);
            
            });

        }

        public static function issuedDocumentData(array $values = []): self
        {

            $eiData = new self();

            # Valorizziamo in modo esplicito tutti i campi supportati da ei_data.
            self::setIfNotEmpty($eiData, 'setVatKind', $values['vat_kind'] ?? null);
            self::setIfNotEmpty($eiData, 'setOriginalDocumentType', $values['original_document_type'] ?? null);
            self::setIfNotEmpty($eiData, 'setOdNumber', $values['od_number'] ?? null);
            self::setIfNotEmpty($eiData, 'setCig', $values['cig'] ?? null);
            self::setIfNotEmpty($eiData, 'setCup', $values['cup'] ?? null);
            self::setIfNotEmpty($eiData, 'setPaymentMethod', $values['payment_method'] ?? null);
            self::setIfNotEmpty($eiData, 'setBankName', $values['bank_name'] ?? null);
            self::setIfNotEmpty($eiData, 'setBankIban', $values['bank_iban'] ?? null);
            self::setIfNotEmpty($eiData, 'setBankBeneficiary', $values['bank_beneficiary'] ?? null);
            self::setIfNotEmpty($eiData, 'setInvoiceNumber', $values['invoice_number'] ?? null);

            $odDate = self::toDateTime($values['od_date'] ?? null);
            if ($odDate !== null) {
                $eiData->setOdDate($odDate);
            }

            $invoiceDate = self::toDateTime($values['invoice_date'] ?? null);
            if ($invoiceDate !== null) {
                $eiData->setInvoiceDate($invoiceDate);
            }

            return $eiData;

        }

        private static function setIfNotEmpty(object $target, string $method, mixed $value): void
        {

            if (!method_exists($target, $method)) {
                return;
            }

            $text = trim((string)$value);
            if ($text === '') {
                return;
            }

            $target->{$method}($text);

        }

        private static function toDateTime(mixed $value): ?\DateTime
        {

            if ($value instanceof \DateTime) {
                return $value;
            }

            if ($value instanceof \DateTimeInterface) {
                return new \DateTime($value->format('c'));
            }

            $text = trim((string)$value);
            if ($text === '') {
                return null;
            }

            try {
                return new \DateTime($text);
            } catch (\Throwable) {
                return null;
            }

        }

    }
