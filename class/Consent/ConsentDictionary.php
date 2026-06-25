<?php

    namespace Wonder\Consent;

    /**
     * Dizionario centralizzato dei valori consentiti
     * per documenti, consensi, azioni, stati e sorgenti.
     */
    class ConsentDictionary
    {
        public const DOC_TYPE_PRIVACY_POLICY = 'privacy_policy';
        public const DOC_TYPE_TERMS = 'terms_conditions';
        public const DOC_TYPE_TERMS_LEGACY = 'terms';
        public const DOC_TYPE_COOKIE_POLICY = 'cookie_policy';

        public const CONSENT_TYPE_PRIVACY_ACK = 'privacy_ack';
        public const CONSENT_TYPE_TERMS_ACCEPT = 'terms_accept';

        public const ACTION_ACCEPT = 'accept';
        public const ACTION_REJECT = 'reject';
        public const ACTION_WITHDRAW = 'withdraw';

        public const STATUS_ACCEPTED = 'accepted';
        public const STATUS_REJECTED = 'rejected';
        public const STATUS_WITHDRAWN = 'withdrawn';
        public const STATUS_PENDING = 'pending';

        public const SOURCE_WEB = 'web';
        public const SOURCE_APP = 'app';
        public const SOURCE_API = 'api';
        public const SOURCE_ADMIN = 'admin';

        /**
         * Tipi documento base usati nei flussi consenso standard.
         * Nota: legal_documents.doc_type supporta anche tipi custom.
         */
        public static function documentTypes(): array
        {

            return [
                self::DOC_TYPE_PRIVACY_POLICY,
                self::DOC_TYPE_TERMS,
                self::DOC_TYPE_COOKIE_POLICY,
            ];

        }

        /**
         * Normalizza il tipo documento.
         * Supporta tipi custom oltre ai documenti base.
         * Esempi validi: privacy_policy, terms_conditions, refund_policy, legal_notice.
         */
        public static function normalizeDocumentType(string $docType): string
        {

            $docType = strtolower(trim($docType));
            $docType = preg_replace('/[^a-z0-9_-]/', '', $docType) ?? '';

            return $docType;

        }

        public static function consentTypes(): array
        {

            return [
                self::CONSENT_TYPE_PRIVACY_ACK,
                self::CONSENT_TYPE_TERMS_ACCEPT,
            ];

        }

        /**
         * Normalizza il consent_type.
         * Supporta anche consent_type custom derivati da doc_type.
         */
        public static function normalizeConsentType(string $consentType): string
        {

            $consentType = strtolower(trim($consentType));
            $consentType = preg_replace('/[^a-z0-9_-]/', '', $consentType) ?? '';

            if ($consentType === '') {
                return '';
            }

            return substr($consentType, 0, 120);

        }

        /**
         * Risolve il consent_type da doc_type.
         *
         * - terms/terms_conditions -> terms_accept
         * - privacy_policy -> privacy_ack
         * - altri tipi -> doc_<doc_type>
         */
        public static function consentTypeFromDocumentType(string $docType): string
        {

            $docType = self::normalizeDocumentType($docType);

            if ($docType === '') {
                return '';
            }

            if (self::isTermsDocumentType($docType)) {
                return self::CONSENT_TYPE_TERMS_ACCEPT;
            }

            if (self::isPrivacyDocumentType($docType)) {
                return self::CONSENT_TYPE_PRIVACY_ACK;
            }

            return self::normalizeConsentType('doc_'.$docType);

        }

        public static function isTermsDocumentType(string $docType): bool
        {

            $docType = self::normalizeDocumentType($docType);

            return in_array($docType, [ self::DOC_TYPE_TERMS, self::DOC_TYPE_TERMS_LEGACY ], true);

        }

        public static function isPrivacyDocumentType(string $docType): bool
        {

            $docType = self::normalizeDocumentType($docType);

            return $docType === self::DOC_TYPE_PRIVACY_POLICY;

        }

        public static function actions(): array
        {

            return [
                self::ACTION_ACCEPT,
                self::ACTION_REJECT,
                self::ACTION_WITHDRAW,
            ];

        }

        public static function statuses(): array
        {

            return [
                self::STATUS_ACCEPTED,
                self::STATUS_REJECTED,
                self::STATUS_WITHDRAWN,
                self::STATUS_PENDING,
            ];

        }

        public static function sources(): array
        {

            return [
                self::SOURCE_WEB,
                self::SOURCE_APP,
                self::SOURCE_API,
                self::SOURCE_ADMIN,
            ];

        }

        /**
         * Normalizza il codice lingua su 2 lettere (es. it, de, en).
         */
        public static function normalizeLanguageCode(string $languageCode, string $fallback = 'it'): string
        {

            $languageCode = strtolower(trim($languageCode));
            $languageCode = preg_replace('/[^a-z]/', '', $languageCode) ?? '';
            $languageCode = substr($languageCode, 0, 2);

            if ($languageCode === '') {
                return $fallback;
            }

            return $languageCode;

        }

        /**
         * Valida che un valore appartenga a una whitelist.
         *
         * @throws ConsentException
         */
        public static function assertAllowed(string $value, array $allowed, string $label): void
        {

            if (!in_array($value, $allowed, true)) {
                throw new ConsentException("Valore non valido per {$label}: {$value}");
            }

        }

        /**
         * Stato derivato dall'azione evento.
         *
         * @throws ConsentException
         */
        public static function statusFromAction(string $action): string
        {

            switch ($action) {
                case self::ACTION_ACCEPT:
                    return self::STATUS_ACCEPTED;
                case self::ACTION_REJECT:
                    return self::STATUS_REJECTED;
                case self::ACTION_WITHDRAW:
                    return self::STATUS_WITHDRAWN;
                default:
                    throw new ConsentException("Azione non valida: {$action}");
            }

        }
    }
