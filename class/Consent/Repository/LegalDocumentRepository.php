<?php

    namespace Wonder\Consent\Repository;

    use Wonder\Consent\ConsentDictionary;
    use Wonder\Consent\ConsentException;

    class LegalDocumentRepository extends AbstractConsentRepository
    {
        /**
         * @return array<string, mixed>|null
         * @throws ConsentException
         */
        public function findActiveByTypeAndLanguage(string $docType, string $languageCode): ?array
        {

            $docType = ConsentDictionary::normalizeDocumentType($docType);

            if ($docType === '') {
                throw new ConsentException('doc_type non valido');
            }

            $languageCode = ConsentDictionary::normalizeLanguageCode($languageCode);

            $result = $this->query->Select(
                'legal_documents',
                [
                    'doc_type' => $docType,
                    'language_code' => $languageCode,
                    'is_active' => 1
                ],
                1,
                'published_at DESC, id',
                'DESC'
            );

            if (!$result->exists || !is_array($result->row)) {
                return null;
            }

            return $result->row;

        }

        /**
         * @return array<string, mixed>|null
         */
        public function findById(int $id): ?array
        {

            if ($id <= 0) {
                return null;
            }

            $result = $this->query->Select(
                'legal_documents',
                [ 'id' => $id ],
                1
            );

            if (!$result->exists || !is_array($result->row)) {
                return null;
            }

            return $result->row;

        }
    }
