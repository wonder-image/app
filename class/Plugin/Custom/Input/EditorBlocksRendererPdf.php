<?php

namespace Wonder\Plugin\Custom\Input;

use Wonder\Pdf;

class EditorBlocksRendererPdf
{
    private array $config = [];

    public function __construct(array $config = [])
    {
        $this->config = array_merge([
            'margin_left' => 18.0,
            'margin_right' => 18.0,
            'margin_top' => 18.0,
            'margin_bottom' => 18.0,
            'max_image_height' => 105.0,
            // Un solo font per tutto il documento.
            'font_regular' => 'NunitoSans-Regular',
            'font_bold' => 'NunitoSans-Bold',
            'text_color' => [38, 38, 38],
            'heading_color' => [20, 20, 20],
            'muted_color' => [112, 112, 112],
            'accent_color' => [96, 96, 96],
            'border_color' => [214, 214, 214],
            'soft_fill_color' => [246, 246, 246],
            'quote_fill_color' => [242, 242, 242],
            'table_header_fill_color' => [235, 235, 235],
            'table_alt_fill_color' => [249, 249, 249],
            'render_unknown_blocks' => false,
            'unknown_block_callback' => null,
        ], $config);
    }

    public static function make(mixed $payload, ?Pdf $pdf = null, array $config = []): Pdf
    {
        return (new self($config))->render($payload, $pdf);
    }

    public function render(mixed $payload, ?Pdf $pdf = null): Pdf
    {
        $pdf ??= new Pdf('P', 'mm', 'A4');

        $pdf->SetMargins(
            (float) $this->config['margin_left'],
            (float) $this->config['margin_top'],
            (float) $this->config['margin_right']
        );
        $pdf->SetAutoPageBreak(true, (float) $this->config['margin_bottom']);

        if ($pdf->PageNo() === 0) {
            $pdf->AddPage();
        }

        $blocks = $this->extractBlocks($payload);

        if ($blocks === []) {
            return $pdf;
        }

        foreach ($blocks as $block) {
            $this->renderBlock($pdf, $this->toArray($block));
        }

        return $pdf;
    }

    private function renderBlock(Pdf $pdf, array $block): void
    {
        $type = strtolower((string) ($block['type'] ?? ''));
        $data = $this->toArray($block['data'] ?? []);

        match ($type) {
            'header', 'paragraph' => $this->renderTextBlock($pdf, $block, $data),
            'image' => $this->renderImageBlock($pdf, $data),
            'gallery' => $this->renderGalleryBlock($pdf, $data),
            'video' => $this->renderVideoBlock($pdf, $data),
            'embed' => $this->renderEmbedBlock($pdf, $data),
            'list' => $this->renderListBlock($pdf, $data),
            'quote' => $this->renderQuoteBlock($pdf, $data),
            'delimiter' => $this->renderDelimiterBlock($pdf),
            'table' => $this->renderTableBlock($pdf, $data),
            'code' => $this->renderCodeBlock($pdf, $data),
            'attaches' => $this->renderAttachBlock($pdf, $data),
            default => $this->renderUnknownBlock($pdf, $block)
        };
    }

    private function renderTextBlock(Pdf $pdf, array $block, array $data): void
    {
        $text = $this->normalizeRichText((string) ($data['text'] ?? ''));

        if ($text === '') {
            return;
        }

        $level = (string) ($data['level'] ?? '');
        $alignment = strtolower((string) ($block['tunes']['textAlign']['alignment'] ?? ($data['alignment'] ?? 'left')));
        $align = match ($alignment) {
            'center' => 'C',
            'right' => 'R',
            default => 'L'
        };

        $isHeading = in_array($level, ['1', '2', '3', '4'], true);
        $fontSize = match ($level) {
            '1' => 23.0,
            '2' => 18.0,
            '3' => 14.2,
            '4' => 12.2,
            default => 11.2
        };
        $lineHeight = match ($level) {
            '1' => 8.8,
            '2' => 7.2,
            '3' => 6.2,
            '4' => 5.8,
            default => 5.9
        };
        $spaceBefore = match ($level) {
            '1' => 3.5,
            '2' => 3.0,
            '3' => 2.2,
            '4' => 1.6,
            default => 0.6
        };
        $spaceAfter = match ($level) {
            '1' => 4.0,
            '2' => 3.2,
            '3' => 2.4,
            '4' => 2.0,
            default => 2.2
        };

        $this->ensureVerticalSpace($pdf, ($lineHeight * 2) + $spaceBefore + $spaceAfter);

        if ($spaceBefore > 0) {
            $pdf->Ln($spaceBefore);
        }

        if ($isHeading) {
            $this->setTextColor($pdf, $this->config['heading_color']);
            $this->useFont($pdf, $fontSize, $level !== '4');
        } else {
            $this->setTextColor($pdf, $this->config['text_color']);
            $this->useFont($pdf, $fontSize);
        }

        $pdf->SetX($this->leftMargin());
        $pdf->MultiCell($this->contentWidth($pdf), $lineHeight, $text, 0, $align);

        if ($level === '1') {
            $this->setDrawColor($pdf, $this->config['accent_color']);
            $pdf->SetLineWidth(0.45);
            $pdf->Line($this->leftMargin(), $pdf->GetY() + 1.2, $this->leftMargin() + 28, $pdf->GetY() + 1.2);
            $pdf->Ln(1.6);
        }

        $pdf->Ln($spaceAfter);
    }

    private function renderImageBlock(Pdf $pdf, array $data): void
    {
        $file = $this->normalizeFile($data['file'] ?? null);
        $path = $this->resolveImagePath((string) ($file['original'] ?? ($file['url'] ?? '')));
        $caption = $this->normalizeRichText((string) ($data['caption'] ?? ''));

        if ($path === null || !is_file($path)) {
            $title = $caption !== '' ? $caption : 'Immagine non incorporabile nel PDF';
            $detail = (string) ($file['url'] ?? '');
            $this->renderInfoCard($pdf, 'IMMAGINE', $title, $detail);
            return;
        }

        $imageInfo = @getimagesize($path);

        if (!is_array($imageInfo) || empty($imageInfo[0]) || empty($imageInfo[1])) {
            $this->renderInfoCard($pdf, 'IMMAGINE', $caption !== '' ? $caption : basename($path), $path);
            return;
        }

        $availableWidth = $this->contentWidth($pdf);
        $displayWidth = $availableWidth;
        $displayHeight = min(
            (float) $this->config['max_image_height'],
            ($imageInfo[1] / $imageInfo[0]) * $displayWidth
        );

        $captionHeight = 0.0;
        if ($caption !== '') {
            $this->useFont($pdf, 9.5);
            $captionHeight = $pdf->MultiCellHeight($displayWidth, 4.8, $caption, 9.5, 'L', false);
        }

        $this->ensureVerticalSpace($pdf, $displayHeight + $captionHeight + 7);

        $x = $this->leftMargin();
        $y = $pdf->GetY() + 1.2;

        $pdf->Ln(1.2);
        $this->setDrawColor($pdf, $this->config['border_color']);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $displayWidth, $displayHeight);
        $pdf->Image($path, $x, $y, $displayWidth, $displayHeight);
        $pdf->SetY($y + $displayHeight);

        if ($caption !== '') {
            $pdf->Ln(2.0);
            $this->useFont($pdf, 9.5);
            $this->setTextColor($pdf, $this->config['muted_color']);
            $pdf->SetX($this->leftMargin());
            $pdf->MultiCell($displayWidth, 4.8, $caption, 0, 'L');
        }

        $pdf->Ln(3.0);
    }

    private function renderGalleryBlock(Pdf $pdf, array $data): void
    {
        $files = $this->toArray($data['files'] ?? []);

        if ($files === []) {
            return;
        }

        foreach ($files as $index => $fileRow) {
            $file = $this->normalizeFile($fileRow);
            $caption = (string) ($file['caption'] ?? ($data['caption'] ?? ''));
            $this->renderImageBlock($pdf, [
                'file' => $file,
                'caption' => $caption !== '' ? $caption : 'Immagine '.((int) $index + 1),
            ]);
        }
    }

    private function renderVideoBlock(Pdf $pdf, array $data): void
    {
        $file = $this->normalizeFile($data['file'] ?? null);
        $url = $this->toString($file['url'] ?? ($data['url'] ?? ''));
        $mimeType = $this->toString($file['mime-type'] ?? ($file['mimeType'] ?? ($data['mime-type'] ?? '')));

        $detail = trim($mimeType !== '' ? $mimeType.' '.$url : $url);

        $this->renderInfoCard(
            $pdf,
            'VIDEO',
            'Contenuto video disponibile nella versione web',
            $detail
        );
    }

    private function renderEmbedBlock(Pdf $pdf, array $data): void
    {
        $src = $this->toString($data['source'] ?? '');

        $this->renderInfoCard(
            $pdf,
            'EMBED',
            'Contenuto incorporato disponibile nella versione web',
            $src
        );
    }

    private function renderListBlock(Pdf $pdf, array $data): void
    {
        $items = $this->toArray($data['items'] ?? []);

        if ($items === []) {
            return;
        }

        $style = strtolower((string) ($data['style'] ?? 'unordered'));

        $pdf->Ln(1.0);
        $this->renderListItems($pdf, $items, $style, 0);
        $pdf->Ln(2.2);
    }

    private function renderListItems(Pdf $pdf, array $items, string $style, int $depth): void
    {
        $orderedIndex = 1;

        foreach ($items as $rawItem) {
            $item = $this->toArray($rawItem);
            $text = '';
            $nestedItems = [];
            $nestedStyle = $style;

            if ($item !== [] && array_key_exists('content', $item)) {
                $text = $this->normalizeRichText((string) ($item['content'] ?? ''));
                $nestedItems = $this->toArray($item['items'] ?? []);
                $nestedStyle = strtolower((string) ($item['style'] ?? $style));
            } elseif ($item !== [] && array_is_list($item)) {
                $nestedItems = $item;
            } elseif (is_scalar($rawItem)) {
                $text = $this->normalizeRichText((string) $rawItem);
            }

            if ($text !== '') {
                $marker = ($style === 'ordered') ? $orderedIndex.'.' : $this->normalizePlainText('•');
                $this->renderListItemLine($pdf, $marker, $text, $depth);
                $orderedIndex++;
            }

            if ($nestedItems !== []) {
                $this->renderListItems($pdf, $nestedItems, $nestedStyle, $depth + 1);
            }
        }
    }

    private function renderListItemLine(Pdf $pdf, string $marker, string $text, int $depth): void
    {
        $indent = $depth * 6.5;
        $markerWidth = 6.0;
        $lineHeight = 5.8;
        $x = $this->leftMargin() + $indent;
        $contentWidth = $this->contentWidth($pdf) - $indent;

        $this->ensureVerticalSpace($pdf, $lineHeight + 3.5);

        $startY = $pdf->GetY();

        $this->useFont($pdf, 11.0, true);
        $this->setTextColor($pdf, $this->config['accent_color']);
        $pdf->SetXY($x, $startY);
        $pdf->Cell($markerWidth, $lineHeight, $marker, 0, 0, 'L');

        $this->useFont($pdf, 11.0);
        $this->setTextColor($pdf, $this->config['text_color']);
        $pdf->SetXY($x + $markerWidth, $startY);
        $pdf->MultiCell($contentWidth - $markerWidth, $lineHeight, $text, 0, 'L');

        $pdf->Ln(0.8);
    }

    private function renderQuoteBlock(Pdf $pdf, array $data): void
    {
        $text = $this->normalizeRichText((string) ($data['text'] ?? ''));

        if ($text === '') {
            return;
        }

        $caption = $this->normalizeRichText((string) ($data['caption'] ?? ''));
        $alignment = strtolower((string) ($data['alignment'] ?? 'left'));
        $align = $alignment === 'center' ? 'C' : 'L';
        $innerWidth = $this->contentWidth($pdf) - 14.0;

        $this->useFont($pdf, 15.0);
        $textHeight = $pdf->MultiCellHeight($innerWidth, 6.6, $this->normalizePlainText('“').$text.$this->normalizePlainText('”'), 15.0, $align, false);

        $captionHeight = 0.0;
        if ($caption !== '') {
            $this->useFont($pdf, 9.5);
            $captionHeight = $pdf->MultiCellHeight($innerWidth, 4.8, $caption, 9.5, $align, false);
        }

        $boxHeight = max(22.0, $textHeight + $captionHeight + 12.0);

        $this->ensureVerticalSpace($pdf, $boxHeight + 4.0);

        $x = $this->leftMargin();
        $y = $pdf->GetY() + 1.2;
        $width = $this->contentWidth($pdf);

        $pdf->Ln(1.2);
        $this->setFillColor($pdf, $this->config['quote_fill_color']);
        $this->setDrawColor($pdf, $this->config['border_color']);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $width, $boxHeight, 'DF');

        $this->setDrawColor($pdf, $this->config['accent_color']);
        $pdf->SetLineWidth(0.9);
        $pdf->Line($x + 4.0, $y + 4.0, $x + 4.0, $y + $boxHeight - 4.0);

        $pdf->SetXY($x + 8.0, $y + 4.0);
        $this->useFont($pdf, 15.0);
        $this->setTextColor($pdf, $this->config['heading_color']);
        $pdf->MultiCell($innerWidth, 6.6, $this->normalizePlainText('“').$text.$this->normalizePlainText('”'), 0, $align);

        if ($caption !== '') {
            $pdf->Ln(0.6);
            $this->useFont($pdf, 9.5);
            $this->setTextColor($pdf, $this->config['muted_color']);
            $pdf->SetX($x + 8.0);
            $pdf->MultiCell($innerWidth, 4.8, $caption, 0, $align);
        }

        $pdf->SetY($y + $boxHeight);
        $pdf->Ln(3.0);
    }

    private function renderDelimiterBlock(Pdf $pdf): void
    {
        $this->ensureVerticalSpace($pdf, 8.0);
        $pdf->Ln(2.0);
        $centerY = $pdf->GetY() + 1.0;
        $startX = $this->leftMargin() + ($this->contentWidth($pdf) * 0.2);
        $endX = $this->leftMargin() + ($this->contentWidth($pdf) * 0.8);

        $this->setDrawColor($pdf, $this->config['border_color']);
        $pdf->SetLineWidth(0.25);
        $pdf->Line($startX, $centerY, $endX, $centerY);

        $this->setDrawColor($pdf, $this->config['accent_color']);
        $pdf->SetLineWidth(0.45);
        $pdf->Line($startX + 12.0, $centerY, $endX - 12.0, $centerY);
        $pdf->Ln(4.0);
    }

    private function renderTableBlock(Pdf $pdf, array $data): void
    {
        $rows = $this->toArray($data['content'] ?? []);

        if ($rows === []) {
            return;
        }

        $columnCount = 0;

        foreach ($rows as $row) {
            $columns = $this->toArray($row);
            $columnCount = max($columnCount, count($columns));
        }

        if ($columnCount <= 0) {
            return;
        }

        $withHeadings = (bool) ($data['withHeadings'] ?? true);
        $columnWidth = $this->contentWidth($pdf) / $columnCount;
        $rowIndex = 0;

        $pdf->Ln(1.0);

        foreach ($rows as $row) {
            $columns = array_values($this->toArray($row));
            $isHeader = $withHeadings && $rowIndex === 0;
            $fontSize = $isHeader ? 10.2 : 9.8;
            $lineHeight = $isHeader ? 5.2 : 4.9;
            $rowHeight = 0.0;

            $this->useFont($pdf, $fontSize, $isHeader);

            for ($i = 0; $i < $columnCount; $i++) {
                $cellText = $this->normalizeRichText((string) ($columns[$i] ?? ''));
                $cellHeight = $pdf->MultiCellHeight($columnWidth - 5.0, $lineHeight, $cellText, $fontSize, 'L', $isHeader) + 5.0;
                $rowHeight = max($rowHeight, $cellHeight);
            }

            $this->ensureVerticalSpace($pdf, $rowHeight + 1.0);

            $startX = $this->leftMargin();
            $startY = $pdf->GetY();

            for ($i = 0; $i < $columnCount; $i++) {
                $cellText = $this->normalizeRichText((string) ($columns[$i] ?? ''));
                $cellX = $startX + ($i * $columnWidth);

                if ($isHeader) {
                    $this->setFillColor($pdf, $this->config['table_header_fill_color']);
                } elseif ($rowIndex % 2 === 0) {
                    $this->setFillColor($pdf, $this->config['table_alt_fill_color']);
                } else {
                    $this->setFillColor($pdf, [255, 255, 255]);
                }

                $this->setDrawColor($pdf, $this->config['border_color']);
                $pdf->SetLineWidth(0.2);
                $pdf->Rect($cellX, $startY, $columnWidth, $rowHeight, 'DF');

                $this->useFont($pdf, $fontSize, $isHeader);
                $this->setTextColor($pdf, $isHeader ? $this->config['heading_color'] : $this->config['text_color']);
                $pdf->SetXY($cellX + 2.5, $startY + 2.2);
                $pdf->MultiCell($columnWidth - 5.0, $lineHeight, $cellText, 0, 'L');
            }

            $pdf->SetY($startY + $rowHeight);
            $rowIndex++;
        }

        $pdf->Ln(3.2);
    }

    private function renderCodeBlock(Pdf $pdf, array $data): void
    {
        $code = $this->normalizePlainText((string) ($data['code'] ?? ''));

        if ($code === '') {
            return;
        }

        $fontSize = 9.2;
        $lineHeight = 4.8;
        $innerWidth = $this->contentWidth($pdf) - 8.0;

        $this->useFont($pdf, $fontSize);
        $codeHeight = $pdf->MultiCellHeight($innerWidth, $lineHeight, $code, $fontSize, 'L', false);
        $boxHeight = $codeHeight + 8.0;

        $this->ensureVerticalSpace($pdf, $boxHeight + 3.0);

        $x = $this->leftMargin();
        $y = $pdf->GetY() + 1.0;
        $width = $this->contentWidth($pdf);

        $pdf->Ln(1.0);
        $this->setFillColor($pdf, $this->config['soft_fill_color']);
        $this->setDrawColor($pdf, $this->config['border_color']);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $width, $boxHeight, 'DF');

        $this->setDrawColor($pdf, $this->config['accent_color']);
        $pdf->SetLineWidth(0.45);
        $pdf->Line($x, $y, $x + $width, $y);

        $pdf->SetXY($x + 4.0, $y + 4.0);
        $this->useFont($pdf, $fontSize);
        $this->setTextColor($pdf, $this->config['text_color']);
        $pdf->MultiCell($innerWidth, $lineHeight, $code, 0, 'L');

        $pdf->SetY($y + $boxHeight);
        $pdf->Ln(3.0);
    }

    private function renderAttachBlock(Pdf $pdf, array $data): void
    {
        $file = $this->normalizeFile($data['file'] ?? null);
        $title = $this->toString($data['title'] ?? ($file['name'] ?? 'Allegato'));
        $fileUrl = $this->toString($file['url'] ?? '');
        $fileSize = (int) ($file['size'] ?? 0);
        $extension = strtolower($this->toString($file['extension'] ?? pathinfo((string) parse_url($fileUrl, PHP_URL_PATH), PATHINFO_EXTENSION)));
        $detail = trim($this->formatAttachmentDetail($extension, $fileSize, $fileUrl));

        $this->renderInfoCard($pdf, 'ALLEGATO', $title, $detail);
    }

    private function renderUnknownBlock(Pdf $pdf, array $block): void
    {
        $callback = $this->config['unknown_block_callback'] ?? null;

        if (is_callable($callback)) {
            call_user_func($callback, $pdf, $block, $this->config);
            return;
        }

        if (empty($this->config['render_unknown_blocks'])) {
            return;
        }

        $type = (string) ($block['type'] ?? 'unknown');
        $this->renderInfoCard($pdf, 'BLOCCO NON SUPPORTATO', $type, '');
    }

    private function renderInfoCard(Pdf $pdf, string $eyebrow, string $title, string $detail): void
    {
        $eyebrow = $this->normalizePlainText($eyebrow);
        $title = $this->normalizeRichText($title);
        $detail = $this->normalizeRichText($detail);

        $this->useFont($pdf, 8.6, true);
        $eyebrowHeight = $pdf->MultiCellHeight($this->contentWidth($pdf) - 10.0, 4.4, $eyebrow, 8.6, 'L', true);

        $this->useFont($pdf, 13.0, true);
        $titleHeight = $pdf->MultiCellHeight($this->contentWidth($pdf) - 10.0, 5.6, $title, 13.0, 'L', true);

        $detailHeight = 0.0;
        if ($detail !== '') {
            $this->useFont($pdf, 9.6);
            $detailHeight = $pdf->MultiCellHeight($this->contentWidth($pdf) - 10.0, 4.8, $detail, 9.6, 'L', false);
        }

        $boxHeight = max(16.0, $eyebrowHeight + $titleHeight + $detailHeight + 10.0);

        $this->ensureVerticalSpace($pdf, $boxHeight + 3.0);

        $x = $this->leftMargin();
        $y = $pdf->GetY() + 1.0;
        $width = $this->contentWidth($pdf);

        $pdf->Ln(1.0);
        $this->setFillColor($pdf, $this->config['soft_fill_color']);
        $this->setDrawColor($pdf, $this->config['border_color']);
        $pdf->SetLineWidth(0.25);
        $pdf->Rect($x, $y, $width, $boxHeight, 'DF');

        $this->setFillColor($pdf, $this->config['accent_color']);
        $pdf->Rect($x, $y, 3.0, $boxHeight, 'F');

        $pdf->SetXY($x + 6.0, $y + 3.4);
        $this->useFont($pdf, 8.6, true);
        $this->setTextColor($pdf, $this->config['accent_color']);
        $pdf->MultiCell($width - 10.0, 4.4, $eyebrow, 0, 'L');

        $pdf->SetX($x + 6.0);
        $this->useFont($pdf, 13.0, true);
        $this->setTextColor($pdf, $this->config['heading_color']);
        $pdf->MultiCell($width - 10.0, 5.6, $title, 0, 'L');

        if ($detail !== '') {
            $pdf->Ln(0.2);
            $pdf->SetX($x + 6.0);
            $this->useFont($pdf, 9.6);
            $this->setTextColor($pdf, $this->config['muted_color']);
            $pdf->MultiCell($width - 10.0, 4.8, $detail, 0, 'L');
        }

        $pdf->SetY($y + $boxHeight);
        $pdf->Ln(3.0);
    }

    private function formatAttachmentDetail(string $extension, int $bytes, string $url): string
    {
        $parts = [];

        if ($extension !== '') {
            $parts[] = strtoupper($extension);
        }

        if ($bytes > 0) {
            $parts[] = $this->formatSize($bytes);
        }

        if ($url !== '') {
            $parts[] = $url;
        }

        return implode(' · ', $parts);
    }

    private function extractBlocks(mixed $payload): array
    {
        if (is_string($payload)) {
            $payload = trim($payload);

            if ($payload === '') {
                return [];
            }

            $decoded = json_decode($payload, true);

            if (json_last_error() === JSON_ERROR_NONE) {
                $payload = $decoded;
            } else {
                return [];
            }
        }

        $payload = $this->toArray($payload);

        if ($payload === []) {
            return [];
        }

        if (isset($payload['paragraph']) && is_array($payload['paragraph'])) {
            return $payload['paragraph'];
        }

        if (isset($payload['blocks']) && is_array($payload['blocks'])) {
            return $payload['blocks'];
        }

        if (isset($payload['type']) && is_string($payload['type'])) {
            return [$payload];
        }

        return array_is_list($payload) ? $payload : [];
    }

    private function normalizeFile(mixed $file): array
    {
        if (is_string($file)) {
            return [
                'url' => $file,
                'original' => $file,
                'large' => $file,
            ];
        }

        return $this->toArray($file);
    }

    private function normalizeRichText(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return (string) \printPDF(\htmlToText($value));
    }

    private function normalizePlainText(string $value): string
    {
        $value = trim($value);

        if ($value === '') {
            return '';
        }

        return (string) \printPDF($value);
    }

    private function resolveImagePath(string $value): ?string
    {
        $value = trim($value);

        if ($value === '') {
            return null;
        }

        $path = (string) parse_url($value, PHP_URL_PATH);

        // Cerco prima un file locale, poi provo i path assoluti del progetto.
        foreach ($this->imageCandidates($value, $path) as $candidate) {
            if (is_file($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    private function toArray(mixed $value): array
    {
        if (is_array($value)) {
            return $value;
        }

        if (is_object($value)) {
            $json = json_encode($value);

            if ($json === false) {
                return [];
            }

            $decoded = json_decode($json, true);

            return is_array($decoded) ? $decoded : [];
        }

        return [];
    }

    private function toString(mixed $value): string
    {
        if (is_scalar($value)) {
            return trim((string) $value);
        }

        return '';
    }

    private function imageCandidates(string $value, string $path): array
    {
        $documentRoot = trim((string) ($_SERVER['DOCUMENT_ROOT'] ?? ''));
        $projectRoot = trim($this->globalString('ROOT'));
        $absolutePath = ($path !== '' && str_starts_with($path, '/')) ? $path : '';

        return array_filter([
            $value,
            $absolutePath,
            $documentRoot !== '' && $absolutePath !== '' ? rtrim($documentRoot, '/').$absolutePath : '',
            $projectRoot !== '' && $absolutePath !== '' ? rtrim($projectRoot, '/').$absolutePath : '',
        ]);
    }

    private function globalString(string $key): string
    {
        $value = $GLOBALS[$key] ?? '';

        return is_string($value) ? trim($value) : '';
    }

    private function useFont(Pdf $pdf, float $size, bool $bold = false): void
    {
        $pdf->LoadFont($this->fontRegular(), $this->fontBold());

        if ($bold) {
            $pdf->FontBold($size);
            return;
        }

        $pdf->Font($size);
    }

    private function fontRegular(): string
    {
        return (string) $this->config['font_regular'];
    }

    private function fontBold(): string
    {
        return (string) $this->config['font_bold'];
    }

    private function contentWidth(Pdf $pdf): float
    {
        return $pdf->GetPageWidth() - $this->leftMargin() - $this->rightMargin();
    }

    private function leftMargin(): float
    {
        return (float) $this->config['margin_left'];
    }

    private function rightMargin(): float
    {
        return (float) $this->config['margin_right'];
    }

    private function bottomMargin(): float
    {
        return (float) $this->config['margin_bottom'];
    }

    private function ensureVerticalSpace(Pdf $pdf, float $height): void
    {
        if (($pdf->GetY() + $height) > ($pdf->GetPageHeight() - $this->bottomMargin())) {
            $pdf->AddPage();
        }
    }

    private function setTextColor(Pdf $pdf, array $color): void
    {
        $pdf->SetTextColor((int) ($color[0] ?? 0), (int) ($color[1] ?? 0), (int) ($color[2] ?? 0));
    }

    private function setDrawColor(Pdf $pdf, array $color): void
    {
        $pdf->SetDrawColor((int) ($color[0] ?? 0), (int) ($color[1] ?? 0), (int) ($color[2] ?? 0));
    }

    private function setFillColor(Pdf $pdf, array $color): void
    {
        $pdf->SetFillColor((int) ($color[0] ?? 255), (int) ($color[1] ?? 255), (int) ($color[2] ?? 255));
    }

    private function formatSize(int $bytes): string
    {
        if ($bytes <= 0) {
            return '0 Kb';
        }

        if ($bytes < 1000000) {
            return number_format($bytes / 1000, 1, '.', '').' Kb';
        }

        if ($bytes < 1000000000) {
            return number_format($bytes / 1000000, 1, '.', '').' Mb';
        }

        return number_format($bytes / 1000000000, 1, '.', '').' Gb';
    }
}
