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
        $RETURN->resolvedContentSnapshot = null;
        $RETURN->renderName = '';

        if ($RETURN->exists) {

            // Risolvo i placeholder una volta sola.
            $rawName = $RETURN->name;
            $rawLabel = $RETURN->checkbox_label;
            $rawContent = $RETURN->content_snapshot;

            $RETURN->renderName = trim((string) \Wonder\Localization\TranslationProvider::replace($rawName));

            $label = trim((string) \Wonder\Localization\TranslationProvider::replace($rawLabel));
            $label = preg_replace('#</?p\b[^>]*>#i', '', $label) ?? $label;
            $RETURN->renderLabel = trim($label);

            if (is_string($rawContent)) {
                $decodedPayload = json_decode($rawContent, true);
                if (json_last_error() === JSON_ERROR_NONE) {
                    $rawContent = $decodedPayload;
                }
            }

            $rawContent = \Wonder\Localization\TranslationProvider::replace($rawContent);
            $RETURN->resolvedContentSnapshot = $rawContent;

            $RETURN->renderContent = (new Wonder\Plugin\Custom\Input\EditorBlocksRenderer())::make($rawContent);
            
        }

        return $RETURN;

    }

    function generateLegalDocumentPdf($legalDocumentId, array $config = []): object
    {

        $document = infoLegalDocument($legalDocumentId);

        $RETURN = (object) [
            'success' => false,
            'message' => '',
            'mime_type' => 'application/pdf',
            'output_mode' => 'F',
            'filename' => '',
            'file_path' => '',
            'content' => '',
            'document' => $document,
            'page_count' => 0,
        ];

        if (!$document->exists) {
            $RETURN->message = 'Documento legale non trovato';
            return $RETURN;
        }

        $docType = trim((string) ($document->doc_type ?? 'document'));
        $docName = trim((string) ($document->renderName ?? ($document->name ?? 'Documento legale')));
        $docVersion = trim((string) ($document->version ?? ''));
        $languageCode = strtolower(trim((string) ($document->language_code ?? '')));
        $publishedAt = trim((string) ($document->published_at ?? ''));
        $config = legalDocumentPdfConfig($config, [
            'doc_type' => $docType,
            'version' => $docVersion,
            'language_code' => $languageCode,
        ]);

        $RETURN->filename = $config['filename'];
        $RETURN->output_mode = $config['output_mode'];

        $documentTypeLabel = legalDocumentTypes()[$docType] ?? ucwords(str_replace(['_', '-'], ' ', $docType));
        $documentTypeLabelUpper = legalDocumentPdfUppercase((string) $documentTypeLabel);
        $documentPayload = $document->resolvedContentSnapshot;
        $publishedLabel = legalDocumentPdfPublishedLabel($publishedAt);

        $pdf = new class extends Wonder\Pdf {
            public string $headerTitle = '';
            public string $footerLabel = '';
            public string $fontRegular = 'NunitoSans-Regular';
            public string $fontBold = 'NunitoSans-Bold';

            // Mostro l'intestazione dalla seconda pagina.
            public function useUiFont(float $size, bool $bold = false): void
            {
                $this->LoadFont($this->fontRegular, $this->fontBold);

                if ($bold) {
                    $this->FontBold($size);
                    return;
                }

                $this->Font($size);
            }

            public function Header(): void
            {
                if ($this->PageNo() <= 1) {
                    return;
                }

                $this->SetY(10);
                $this->useUiFont(8);
                $this->SetTextColor(110, 118, 128);
                $this->Cell(0, 5, $this->headerTitle, 0, 1, 'L');
                $this->SetDrawColor(230, 233, 236);
                $this->SetLineWidth(0.2);
                $this->Line($this->lMargin, 16, $this->w - $this->rMargin, 16);
                $this->Ln(3);
            }

            public function Footer(): void
            {
                $this->SetY(-12);
                $this->SetDrawColor(230, 233, 236);
                $this->SetLineWidth(0.2);
                $this->Line($this->lMargin, $this->GetY(), $this->w - $this->rMargin, $this->GetY());
                $this->Ln(2);
                $this->useUiFont(8);
                $this->SetTextColor(110, 118, 128);
                $this->Cell(0, 4, $this->footerLabel.'   Pagina '.$this->PageNo().'/{nb}', 0, 0, 'L');
            }
        };

        $pdf->AliasNbPages();
        $pdf->SetMargins($config['margin_left'], $config['margin_top'], $config['margin_right']);
        $pdf->SetAutoPageBreak(true, $config['margin_bottom']);
        $pdf->headerTitle = printPDF($docName);
        $pdf->footerLabel = printPDF(trim($docName.($docVersion !== '' ? ' · v'.$docVersion : '')));
        $pdf->AddPage();

        $pdf->SetTitle(printPDF($docName));
        $pdf->SetCreator('Wonder Image');

        global $SOCIETY;

        $author = trim((string) ($SOCIETY->legal_name ?? $SOCIETY->name ?? ''));
        if ($author !== '') {
            $pdf->SetAuthor(printPDF($author));
        }

        // Passo al renderer solo ciò che serve davvero.
        $renderer = new Wonder\Plugin\Custom\Input\EditorBlocksRendererPdf($config['renderer']);
        $contentWidth = $pdf->GetPageWidth() - $config['margin_left'] - $config['margin_right'];

        $pdf->LoadFont('NunitoSans-Regular', 'NunitoSans-Bold');
        $pdf->fontRegular = 'NunitoSans-Regular';
        $pdf->fontBold = 'NunitoSans-Bold';

        $pdf->SetTextColor(96, 96, 96);
        $pdf->useUiFont(8.8, true);
        $pdf->SetX($config['margin_left']);
        $pdf->MultiCell($contentWidth, 4.4, printPDF($documentTypeLabelUpper), 0, 'L');

        $pdf->Ln(1.2);
        $pdf->SetTextColor(20, 20, 20);
        $pdf->useUiFont(26, true);
        $pdf->SetX($config['margin_left']);
        $pdf->MultiCell($contentWidth, 9.0, printPDF($docName), 0, 'L');

        $meta = array_filter([
            $docVersion !== '' ? 'Versione '.$docVersion : '',
            $languageCode !== '' ? strtoupper($languageCode) : '',
            $publishedLabel !== '' ? 'Pubblicato '.$publishedLabel : '',
        ]);

        if ($meta !== []) {
            $pdf->Ln(1.0);
            $pdf->useUiFont(10.2);
            $pdf->SetTextColor(112, 112, 112);
            $pdf->SetX($config['margin_left']);
            $pdf->MultiCell($contentWidth, 5.4, printPDF(implode(' · ', $meta)), 0, 'L');
        }

        $pdf->Ln(1.4);
        $pdf->SetDrawColor(96, 96, 96);
        $pdf->SetLineWidth(0.5);
        $pdf->Line($config['margin_left'], $pdf->GetY(), $config['margin_left'] + 32, $pdf->GetY());

        $pdf->Ln(5.0);

        $renderer->render($documentPayload, $pdf);

        $RETURN->page_count = $pdf->PageNo();

        try {
            if ($config['output_mode'] === 'S') {
                $content = $pdf->Output('S');
                $RETURN->content = is_string($content) ? $content : '';
                $RETURN->success = $RETURN->content !== '';
                if (!$RETURN->success) {
                    $RETURN->message = 'Impossibile generare il contenuto PDF';
                }

                return $RETURN;
            }

            $outputPath = legalDocumentPdfOutputPath($config['output_path'], $config['filename']);

            $outputDir = dirname($outputPath);

            if (!is_dir($outputDir)) {
                mkdir($outputDir, 0775, true);
            }

            $pdf->Output('F', $outputPath);

            $RETURN->file_path = $outputPath;
            $RETURN->success = is_file($outputPath);

            if (!$RETURN->success) {
                $RETURN->message = 'Il file PDF non è stato scritto correttamente';
            }

        } catch (Throwable $exception) {

            $RETURN->message = $exception->getMessage();

        }

        return $RETURN;

    }

    function legalDocumentPdfConfig(array $config, array $documentMeta): array
    {

        // Raccolgo qui i default del PDF.
        $filename = (string) ($config['filename'] ?? legalDocumentPdfDefaultFilename($documentMeta));
        $outputMode = strtoupper(trim((string) ($config['output_mode'] ?? 'F')));
        $rendererConfig = $config['renderer'] ?? [];
        $marginLeft = (float) ($config['margin_left'] ?? 18.0);
        $marginRight = (float) ($config['margin_right'] ?? 18.0);
        $marginTop = (float) ($config['margin_top'] ?? 18.0);
        $marginBottom = (float) ($config['margin_bottom'] ?? 18.0);
        $rendererBase = [
            'margin_left' => $marginLeft,
            'margin_right' => $marginRight,
            'margin_top' => $marginTop,
            'margin_bottom' => $marginBottom,
            'font_regular' => 'NunitoSans-Regular',
            'font_bold' => 'NunitoSans-Bold',
        ];

        return [
            'margin_left' => $marginLeft,
            'margin_right' => $marginRight,
            'margin_top' => $marginTop,
            'margin_bottom' => $marginBottom,
            'filename' => legalDocumentPdfNormalizeFilename($filename),
            'output_mode' => in_array($outputMode, ['F', 'S'], true) ? $outputMode : 'F',
            'output_path' => trim((string) ($config['output_path'] ?? '')),
            'renderer' => is_array($rendererConfig)
                ? array_replace($rendererBase, $rendererConfig)
                : $rendererBase,
        ];

    }

    function legalDocumentPdfDefaultFilename(array $documentMeta): string
    {

        $slug = preg_replace(
            '/[^a-z0-9]+/i',
            '-',
            strtolower(
                trim((string) ($documentMeta['doc_type'] ?? ''))
                .'-'.
                trim((string) ($documentMeta['version'] ?? ''))
                .'-'.
                trim((string) ($documentMeta['language_code'] ?? ''))
            )
        );

        $slug = trim((string) $slug, '-');

        return ($slug !== '' ? $slug : 'legal-document').'.pdf';

    }

    function legalDocumentPdfNormalizeFilename(string $filename): string
    {

        $filename = trim($filename);

        if ($filename === '') {
            $filename = 'legal-document.pdf';
        }

        if (!str_ends_with(strtolower($filename), '.pdf')) {
            $filename .= '.pdf';
        }

        return $filename;

    }

    function legalDocumentPdfUppercase(string $value): string
    {

        return function_exists('mb_strtoupper')
            ? mb_strtoupper($value, 'UTF-8')
            : strtoupper($value);

    }

    function legalDocumentPdfPublishedLabel(string $publishedAt): string
    {

        if ($publishedAt === '' || strtotime($publishedAt) === false) {
            return '';
        }

        return date('d/m/Y H:i', strtotime($publishedAt));

    }

    function legalDocumentPdfOutputPath(string $outputPath, string $filename): string
    {

        if ($outputPath === '') {
            return rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.uniqid('legal-document-', true).'-'.$filename;
        }

        if (is_dir($outputPath)) {
            return rtrim($outputPath, DIRECTORY_SEPARATOR).DIRECTORY_SEPARATOR.$filename;
        }

        return $outputPath;

    }
