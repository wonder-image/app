<?php

    /**
     * Seed documenti legali iniziali da traduzioni (__t) per tutte le lingue registrate.
     * Vengono inseriti solo se la combinazione (doc_type, version, language_code) non esiste.
     */

    $legalVersion = '1.0.0';
    $publishedAt = date('Y-m-d H:i:s');
    $initialLang = __l();
    $langs = __ls();
    $docTypes = legalDocumentTypes();

    foreach ($langs as $languageCode => $_langMeta) {

        \Wonder\Localization\LanguageContext::setLang($languageCode);

        foreach ($docTypes as $docType) {

            $labelKey = 'components.forms.fields.'.$docType.'.label';
            $contentKey = 'legal.'.$docType.'.content';
            $checkboxLabel = '';
            $content = null;

            $label = __t($labelKey);
            $content = __t($contentKey);

            $exists = sqlSelect( 'legal_documents', [ 'doc_type' => $docType, 'version' => $legalVersion, 'language_code' => $languageCode ], 1 )->exists;

            if ($exists) { return; }

            $VALUES = Wonder\App\Table::key('legal_documents')->prepare([
                'doc_type' => $docType,
                'version' => $legalVersion,
                'language_code' => $languageCode,
                'checkbox_label' => $label,
                'content_hash' => hash('sha256', $content),
                'content_snapshot' => contentsToEditorBlocks($content),
                'published_at' => $publishedAt,
                'is_active' => 1,
                'created_at' => $publishedAt,
                'updated_at' => $publishedAt,
            ]);

            sqlInsert('legal_documents', $VALUES);
            
        }

    }

    \Wonder\Localization\LanguageContext::setLang($initialLang);