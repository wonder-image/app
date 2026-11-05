<?php

namespace Wonder\App\Import;

use Wonder\App\Dataset\Column;
use InvalidArgumentException;
use PhpOffice\PhpSpreadsheet\Cell\DataType;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PhpOffice\PhpSpreadsheet\Writer\Xlsx;
use Wonder\Backend\Support\SpreadsheetText;

/**
 * Il file di esempio di un tracciato: la riga delle intestazioni
 * (`Layout::sampleColumns()`) e le righe di `Layout::sampleRows()`. Ricaricato
 * così com'è, ogni colonna si abbina da sola.
 */
final class Sample
{
    public const FORMATS = ['xlsx', 'csv'];

    /** @return list<list<string>> la prima riga sono le intestazioni */
    public static function table(Layout $layout): array
    {
        $columns = $layout->sampleColumns();
        $table = [array_map(static fn (Column $column): string => $column->header, $columns)];

        foreach ($layout->sampleRows() as $row) {
            $table[] = array_map(static fn (Column $column): string => (string) ($row[$column->key] ?? ''), $columns);
        }

        return $table;
    }

    public static function render(Layout $layout, string $format): string
    {
        return match ($format) {
            'xlsx' => self::xlsx(self::table($layout)),
            'csv' => self::csv(self::table($layout)),
            default => throw new InvalidArgumentException('Formato non valido: '.$format),
        };
    }

    public static function filename(Layout $layout, string $format): string
    {
        $name = trim((string) preg_replace('/[^a-z0-9]+/', '-', strtolower($layout::key())), '-');

        return 'esempio-'.($name === '' ? 'caricamento' : $name).'.'.$format;
    }

    public static function contentType(string $format): string
    {
        return $format === 'csv'
            ? 'text/csv; charset=utf-8'
            : 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet';
    }

    /** @param list<list<string>> $table */
    private static function xlsx(array $table): string
    {
        $spreadsheet = new Spreadsheet();
        $sheet = $spreadsheet->getActiveSheet();

        foreach ($table as $r => $row) {
            foreach ($row as $c => $value) {
                $sheet->getCell([$c + 1, $r + 1])->setValueExplicit($value, DataType::TYPE_STRING);
            }
        }

        $width = max(1, count($table[0] ?? []));
        $sheet->getStyle([1, 1, $width, 1])->getFont()->setBold(true);

        for ($c = 1; $c <= $width; $c++) {
            $sheet->getColumnDimensionByColumn($c)->setAutoSize(true);
        }

        ob_start();
        (new Xlsx($spreadsheet))->save('php://output');

        return (string) ob_get_clean();
    }

    /** @param list<list<string>> $table */
    private static function csv(array $table): string
    {
        $handle = fopen('php://temp', 'r+');

        foreach ($table as $row) {
            fputcsv($handle, array_map([SpreadsheetText::class, 'cell'], $row), ',', '"', '');
        }

        rewind($handle);
        $csv = (string) stream_get_contents($handle);
        fclose($handle);

        return "\xEF\xBB\xBF".$csv;
    }
}
