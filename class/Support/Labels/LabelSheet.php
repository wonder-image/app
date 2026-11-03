<?php

namespace Wonder\Support\Labels;

use InvalidArgumentException;
use Wonder\Pdf;
use Wonder\Support\Barcode\Ean;

/**
 * Un PDF di etichette su un `LabelFormat`: righe di testo (la prima in
 * grassetto), un valore in evidenza (per esempio il prezzo) e un codice
 * EAN-8 o EAN-13. `plan()` e `layout()` calcolano dove va tutto, `render()`
 * lo disegna: la divisione serve ai test, che in un PDF compresso non
 * vedrebbero niente.
 */
final class LabelSheet
{
    /**
     * Il contenuto di ogni etichetta, una per copia. Un EAN sbagliato non
     * ferma il foglio: esce senza barre, con l'avviso accanto alle cifre.
     *
     * @param list<array{lines?: list<string>, highlight?: string, ean?: string, copies?: int}> $items
     * @return list<array{lines: list<string>, highlight: string, ean: string, modules: ?string, invalid: bool}>
     */
    public static function labels(array $items): array
    {
        $labels = [];

        foreach ($items as $item) {
            $lines = array_values(array_filter(
                array_map(static fn (mixed $line): string => trim((string) $line), (array) ($item['lines'] ?? [])),
                static fn (string $line): bool => $line !== ''
            ));
            $ean = trim((string) ($item['ean'] ?? ''));
            $modules = null;

            if ($ean !== '') {
                try {
                    $encoded = Ean::encode($ean, strlen($ean) === 8 ? 8 : 13);
                    $ean = $encoded['code'];
                    $modules = $encoded['modules'];
                } catch (InvalidArgumentException) {
                    $modules = null;
                }
            }

            $label = [
                'lines' => $lines,
                'highlight' => trim((string) ($item['highlight'] ?? '')),
                'ean' => $ean,
                'modules' => $modules,
                'invalid' => $ean !== '' && $modules === null,
            ];

            for ($copy = 0; $copy < max(0, (int) ($item['copies'] ?? 1)); $copy++) {
                $labels[] = $label;
            }
        }

        return $labels;
    }

    /**
     * Pagina e angolo di ogni etichetta, in mm. `$start` salta le prime
     * caselle di un foglio già usato in parte; nel rotolo non vale.
     *
     * @param list<array<string, mixed>> $labels
     * @return list<array{page: int, x: float, y: float, label: array<string, mixed>}>
     */
    public static function plan(LabelFormat $format, array $labels, int $start = 0): array
    {
        $perPage = $format->perPage();
        $slot = $format->isRoll() ? 0 : max(0, min($start, $perPage - 1));
        $out = [];

        foreach ($labels as $label) {
            $cell = $slot % $perPage;
            $column = $cell % $format->columns;
            $row = intdiv($cell, $format->columns);

            $out[] = [
                'page' => intdiv($slot, $perPage) + 1,
                'x' => round($format->marginLeft + $column * ($format->labelWidth + $format->gapX), 3),
                'y' => round($format->marginTop + $row * ($format->labelHeight + $format->gapY), 3),
                'label' => $label,
            ];

            $slot++;
        }

        return $out;
    }

    /**
     * Le misure dentro un'etichetta, in mm dal suo angolo: righe di testo,
     * valore in evidenza e barre. Le righe che non ci stanno si lasciano
     * dall'ultima, perché le barre non scendano sotto un'altezza leggibile.
     *
     * @param array{lines: list<string>, highlight: string, ean: string, modules: ?string, invalid: bool} $label
     * @return array{padding: float, line: float, font: float, lines: list<string>, highlight: string, bars: ?array{x: float, y: float, module: float, height: float}, digits: ?array{y: float, text: string}}
     */
    public static function layout(LabelFormat $format, array $label): array
    {
        $width = $format->labelWidth;
        $height = $format->labelHeight;
        $padding = min(2.5, $height * 0.08, $width * 0.06);
        $line = min(4.2, max(2.2, $height * 0.125));
        // 1 pt = 0,3528 mm: il carattere occupa circa l'80% della riga.
        $font = round($line / 0.3528 * 0.8, 1);
        $inner = $height - 2 * $padding;

        $lines = $label['lines'];
        $highlight = $label['highlight'] !== '' ? $line * 1.3 : 0.0;
        $hasCode = $label['ean'] !== '';
        $hasBars = $label['modules'] !== null;
        $minBars = $hasBars ? max(5.0, $height * 0.3) : 0.0;
        $fixed = $highlight + ($hasCode ? $line : 0.0);

        while ($lines !== [] && count($lines) * $line + $fixed + $minBars > $inner) {
            array_pop($lines);
        }

        $bars = null;
        $digits = null;

        if ($hasCode) {
            $digitsY = $height - $padding - $line;
            $digits = ['y' => round($digitsY, 3), 'text' => $label['invalid'] ? $label['ean'].' (EAN non valido)' : $label['ean']];

            if ($hasBars) {
                $count = strlen((string) $label['modules']);
                // Margine bianco di 9 moduli per lato, che i lettori vogliono.
                $module = min(0.5, ($width - 2 * $padding) / ($count + 18));
                $top = $padding + count($lines) * $line + $highlight;

                $bars = [
                    'x' => round(($width - $count * $module) / 2, 3),
                    'y' => round($top, 3),
                    'module' => round($module, 4),
                    'height' => round(max(0.0, $digitsY - $top), 3),
                ];
            }
        }

        return [
            'padding' => round($padding, 3),
            'line' => round($line, 3),
            'font' => $font,
            'lines' => $lines,
            'highlight' => $label['highlight'],
            'bars' => $bars,
            'digits' => $digits,
        ];
    }

    /**
     * Il PDF come stringa.
     *
     * @param list<array<string, mixed>> $labels
     */
    public static function render(LabelFormat $format, array $labels, int $start = 0, string $title = 'Etichette'): string
    {
        // FPDF mette sempre in verticale le misure date: il rotolo largo va in orizzontale.
        $orientation = $format->pageWidth > $format->pageHeight ? 'L' : 'P';
        $pdf = new Pdf($orientation, 'mm', [$format->pageWidth, $format->pageHeight]);
        $pdf->SetAutoPageBreak(false);
        $pdf->SetMargins(0, 0, 0);
        $pdf->SetCreator('Wonder Image');
        $pdf->SetTitle(self::text($title));

        $page = 0;
        $plan = self::plan($format, $labels, $start);

        if ($plan === []) {
            $pdf->AddPage();
        }

        foreach ($plan as $slot) {
            while ($page < $slot['page']) {
                $pdf->AddPage();
                $page++;
            }

            self::draw($pdf, $format, (float) $slot['x'], (float) $slot['y'], $slot['label']);
        }

        return $pdf->Output('S');
    }

    /** @param array{lines: list<string>, highlight: string, ean: string, modules: ?string, invalid: bool} $label */
    private static function draw(Pdf $pdf, LabelFormat $format, float $x, float $y, array $label): void
    {
        $box = self::layout($format, $label);
        $padding = $box['padding'];
        $usable = $format->labelWidth - 2 * $padding;
        $cursor = $y + $padding;

        $pdf->SetTextColor(0, 0, 0);
        $pdf->SetFillColor(0, 0, 0);

        foreach ($box['lines'] as $index => $text) {
            $pdf->SetFont('Helvetica', $index === 0 ? 'B' : '', $box['font']);
            $pdf->SetXY($x + $padding, $cursor);
            $pdf->Cell($usable, $box['line'], self::fit($pdf, $text, $usable), 0, 0, 'L');
            $cursor += $box['line'];
        }

        if ($box['highlight'] !== '') {
            $pdf->SetFont('Helvetica', 'B', $box['font'] * 1.3);
            $pdf->SetXY($x + $padding, $cursor);
            $pdf->Cell($usable, $box['line'] * 1.3, self::fit($pdf, $box['highlight'], $usable), 0, 0, 'L');
        }

        if ($box['bars'] !== null) {
            $bars = $box['bars'];
            $left = $x + $bars['x'];

            foreach (str_split((string) $label['modules']) as $index => $bit) {
                if ($bit === '1') {
                    $pdf->Rect($left + $index * $bars['module'], $y + $bars['y'], $bars['module'], $bars['height'], 'F');
                }
            }
        }

        if ($box['digits'] !== null) {
            $pdf->SetFont('Helvetica', '', $box['font']);
            $pdf->SetXY($x + $padding, $y + $box['digits']['y']);
            $pdf->Cell($usable, $box['line'], self::fit($pdf, $box['digits']['text'], $usable), 0, 0, 'C');
        }
    }

    /** Il testo in Windows-1252, accorciato coi puntini se non ci sta. */
    private static function fit(Pdf $pdf, string $text, float $width): string
    {
        $text = self::text($text);

        if ($pdf->GetStringWidth($text) <= $width) {
            return $text;
        }

        while ($text !== '' && $pdf->GetStringWidth($text.'...') > $width) {
            $text = substr($text, 0, -1);
        }

        return rtrim($text).'...';
    }

    /** I font base di FPDF leggono solo Windows-1252. */
    private static function text(string $text): string
    {
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        if (function_exists('convertTextToWindows1252')) {
            return convertTextToWindows1252($text);
        }

        $converted = @iconv('UTF-8', 'Windows-1252//TRANSLIT', $text);

        return is_string($converted) ? $converted : (string) preg_replace('/[^\x20-\x7E]/', '?', $text);
    }
}
