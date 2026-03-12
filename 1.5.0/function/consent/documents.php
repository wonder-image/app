<?php

    /**
     * Ritorna i tipi documento legale disponibili (codice => label).
     *
     * @return array<string, string>
     */
    function legalDocumentTypes(): array
    {

        $types = Wonder\Consent\LegalDocumentTypeContext::getTypes();
        $translatedTypes = [];

        foreach ($types as $code => $fallbackLabel) {

            $translationKey = "components.legal.{$code}";
            $translated = '';

            try {
                $translatedValue = __t($translationKey);
                if (is_string($translatedValue)) {
                    $translated = trim($translatedValue);
                }
            } catch (Throwable $exception) {
                $translated = '';
            }

            if ($translated === '') {
                $translated = $fallbackLabel;
            }

            $translatedTypes[$code] = (string) $translated;

        }

        return $translatedTypes;

    }

    function infoLegalDocument($value, $filter = 'id') {

        $RETURN = info('legal_documents', $filter, $value);
        
        $RETURN->renderLabel = '';
        $RETURN->renderContent = '';

        if ($RETURN->exists) {

            $rawLabel = $RETURN->checkbox_label;
            $rawContent = $RETURN->content_snapshot;

            $RETURN->renderLabel = trim((string) \Wonder\Localization\TranslationProvider::replace($rawLabel));

            if (is_string($rawContent)) {
                $decodedPayload = json_decode($rawContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $rawContent = $decodedPayload;
                }
            }

            $rawContent = \Wonder\Localization\TranslationProvider::replace($rawContent);

            $RETURN->renderContent = (new Wonder\Plugin\Custom\Input\EditorBlocksRenderer())::make($rawContent);
            
        }

        return $RETURN;

    }
