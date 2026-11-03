<?php

namespace Wonder\Localization\Catalog;

use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Wonder\Backend\Support\SpreadsheetText;

/**
 * Il foglio del traduttore: colonne `id`, `contesto`, lingua sorgente,
 * lingua destinazione. Ogni cella è testo (`SpreadsheetText::cell()`).
 */
final class Sheet
{
    public const HEADER_ID = 'id';
    public const HEADER_CONTEXT = 'contesto';

    /**
     * @param array<string, Entry> $entries
     * @return list<list<string>>
     */
    public static function rows(array $entries, string $source, string $target): array
    {
        $rows = [[self::HEADER_ID, self::HEADER_CONTEXT, $source, $target]];

        foreach ($entries as $entry) {
            $rows[] = array_map(
                [SpreadsheetText::class, 'cell'],
                [$entry->id, $entry->context, $entry->source, $entry->target],
            );
        }

        return $rows;
    }

    /** @param list<list<string>> $rows */
    public static function xlsx(array $rows): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();
        $sheet->setTitle('Traduzioni');

        foreach ($rows as $r => $row) {
            foreach (array_values($row) as $c => $value) {
                $sheet->getCell([$c + 1, $r + 1])->setValueExplicit((string) $value, DataType::TYPE_STRING);
            }
        }

        $sheet->getStyle('A1:D1')->getFont()->setBold(true);

        foreach (['A' => 40, 'B' => 20, 'C' => 60, 'D' => 60] as $column => $width) {
            $sheet->getColumnDimension($column)->setWidth($width);
        }

        $sheet->getStyle('C:D')->getAlignment()->setWrapText(true);

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<list<string>> $rows */
    public static function csv(array $rows): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($rows as $row) {
            fputcsv($handle, $row, ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }

    /**
     * Righe del foglio caricato, con le lingue dell'intestazione verificate.
     *
     * @return list<array{id: string, context: string, source: string, target: string}>
     */
    public static function read(string $path, string $extension, string $source, string $target): array
    {
        $matrix = match (strtolower($extension)) {
            'xlsx' => self::readXlsx($path),
            'csv' => self::readCsv($path),
            default => throw new InvalidArgumentException('Formato non supportato: carica un file .xlsx o .csv.'),
        };

        $header = array_map(static fn ($v): string => strtolower(trim((string) $v)), array_shift($matrix) ?? []);

        if (($header[0] ?? '') !== self::HEADER_ID || ($header[2] ?? '') !== strtolower($source)
            || ($header[3] ?? '') !== strtolower($target)) {
            throw new InvalidArgumentException(
                "Intestazione non valida: attese le colonne id, contesto, {$source}, {$target}."
            );
        }

        $rows = [];

        foreach ($matrix as $row) {
            $row = array_map(static fn ($v): string => (string) $v, array_values($row));

            if (trim($row[0] ?? '') === '') {
                continue;
            }

            $rows[] = [
                'id' => trim($row[0]),
                'context' => $row[1] ?? '',
                'source' => $row[2] ?? '',
                'target' => $row[3] ?? '',
            ];
        }

        return $rows;
    }

    /** @return list<list<string>> */
    private static function readXlsx(string $path): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $sheet = $reader->load($path)->getSheet(0);
        } catch (\Throwable) {
            throw new InvalidArgumentException('Il file .xlsx non si può leggere.');
        }

        $matrix = [];

        foreach ($sheet->getRowIterator() as $row) {
            $cells = [];

            foreach ($row->getCellIterator('A', 'D') as $cell) {
                $cells[] = (string) $cell->getValue();
            }

            $matrix[] = $cells;
        }

        return $matrix;
    }

    /** @return list<list<string>> */
    private static function readCsv(string $path): array
    {
        $content = (string) file_get_contents($path);
        $content = str_starts_with($content, "\xEF\xBB\xBF") ? substr($content, 3) : $content;
        $first = strtok($content, "\n") ?: '';
        $delimiter = substr_count($first, ';') > substr_count($first, ',') ? ';' : ',';

        $handle = fopen('php://temp', 'r+');
        fwrite($handle, $content);
        rewind($handle);
        $matrix = [];

        while (($row = fgetcsv($handle, null, $delimiter, '"', '')) !== false) {
            if ($row !== [null]) {
                $matrix[] = array_map(static fn ($v): string => (string) $v, $row);
            }
        }

        fclose($handle);

        return $matrix;
    }
}
