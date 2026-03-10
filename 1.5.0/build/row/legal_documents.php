<?php

    /**
     * Seed documenti legali iniziali in it/de/en.
     * Vengono inseriti solo se la combinazione (doc_type, version, language_code) non esiste.
     */

    $legalVersion = '1.0.0';
    $publishedAt = date('Y-m-d H:i:s');

    $langFiles = [
        'it' => __DIR__.'/../../../resources/lang/it/legal.json',
        'de' => __DIR__.'/../../../resources/lang/de/legal.json',
        'en' => __DIR__.'/../../../resources/lang/en/legal.json',
        'es' => __DIR__.'/../../../resources/lang/es/legal.json',
        'fr' => __DIR__.'/../../../resources/lang/fr/legal.json',
    ];

    $docMap = [
        'privacy_policy' => 'privacy_policy',
        'terms' => 'terms_conditions',
        'cookie_policy' => 'cookie_policy',
        'marketing' => 'marketing',
    ];

    $insertLegalDocument = function (string $docType, string $languageCode, string $title, array $content) use ($legalVersion, $publishedAt) {

        $languageCode = strtolower(trim($languageCode));
        $languageCode = substr(preg_replace('/[^a-z]/', '', $languageCode) ?? '', 0, 2);

        if ($languageCode === '') {
            $languageCode = 'it';
        }

        $contentSnapshot = json_encode($content, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        if ($contentSnapshot === false) {
            $contentSnapshot = '{}';
        }

        $exists = sqlSelect(
            'legal_documents',
            [
                'doc_type' => $docType,
                'version' => $legalVersion,
                'language_code' => $languageCode
            ],
            1
        )->exists;

        if ($exists) {
            return;
        }

        sqlInsert('legal_documents', [
            'doc_type' => $docType,
            'version' => $legalVersion,
            'language_code' => $languageCode,
            'title' => trim($title) !== '' ? $title : strtoupper($docType).' '.$languageCode,
            'content_hash' => hash('sha256', $contentSnapshot),
            'content_snapshot' => $contentSnapshot,
            'published_at' => $publishedAt,
            'is_active' => 1,
            'created_at' => $publishedAt,
            'updated_at' => $publishedAt,
        ]);

    };

    foreach ($langFiles as $languageCode => $filePath) {

        if (!file_exists($filePath)) { continue; }

        $raw = file_get_contents($filePath);
        if ($raw === false) { continue; }

        $decoded = json_decode($raw, true);
        if (!is_array($decoded)) { continue; }

        foreach ($docMap as $docType => $jsonKey) {

            $node = $decoded[$jsonKey] ?? null;
            if (!is_array($node)) { continue; }

            $title = (string) ($node['content']['title'] ?? $node['seo']['title'] ?? '');
            $content = $node['content'] ?? $node;

            if (!is_array($content)) {
                $content = [ 'text' => (string) $content ];
            }

            $insertLegalDocument($docType, $languageCode, $title, $content);

        }

    }
