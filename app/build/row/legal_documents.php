<?php

    /**
     * Seed documenti legali iniziali da traduzioni (senza replacement) per tutte le lingue registrate.
     * Vengono inseriti solo se la combinazione (doc_type, version, language_code) non esiste.
     */

    $legalVersion = '1.0.0';
    $publishedAt = date('Y-m-d H:i:s');
    $initialLang = __l();
    $langs = __ls();
    $docTypes = legalDocumentTypes();

    foreach ($langs as $languageCode => $_langMeta) {

        \Wonder\Localization\LanguageContext::setLang($languageCode);

        foreach ($docTypes as $docTypeCode => $docTypeLabel) {

            $nameKey = 'components.legal.'.$docTypeCode;
            $labelKey = 'components.forms.fields.'.$docTypeCode.'.label';
            $contentKey = 'legal.'.$docTypeCode.'.content';

            try {
                $name = \Wonder\Localization\TranslationProvider::getRaw($nameKey);
            } catch (\Throwable $th) {
                $name = "";
            }

            try {
                $label = \Wonder\Localization\TranslationProvider::getRaw($labelKey);
            } catch (\Throwable $th) {
                $label = "";
            }

            if (!empty($name) && !empty($label)) {
                
                $content = contentsToEditorBlocks(\Wonder\Localization\TranslationProvider::getRaw($contentKey));
                $contentJSON = json_encode($content);

                $exists = sqlSelect( 'legal_documents', [ 'doc_type' => $docTypeCode, 'version' => $legalVersion, 'language_code' => $languageCode ], 1 )->exists;

                if ($exists) { continue; }

                $VALUES = Wonder\App\Table::key('legal_documents')->prepare([
                    'doc_type' => $docTypeCode,
                    'name' => $name,
                    'version' => $legalVersion,
                    'language_code' => $languageCode,
                    'checkbox_label' => $label,
                    'content_hash' => hash('sha256', $contentJSON),
                    'content_snapshot' => $content,
                    'published_at' => $publishedAt,
                ]);

                sqlInsert('legal_documents', $VALUES);
                
            }
            
        }

    }

    \Wonder\Localization\LanguageContext::setLang($initialLang);
