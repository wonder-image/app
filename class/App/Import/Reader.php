<?php

namespace Wonder\App\Import;

use PhpOffice\PhpSpreadsheet\IOFactory;
use Throwable;

/**
 * Legge un file da caricare: .csv (virgola o punto e virgola, con o senza
 * BOM, UTF-8 o Windows-1252) o .xlsx (il primo foglio). La prima riga sono
 * le intestazioni; le righe vuote si saltano, ma il numero di riga resta
 * quello del file.
 */
final class Reader
{
    public const MAX_ROWS = 50000;
    public const EXTENSIONS = ['csv', 'xlsx'];

    /**
     * @return array{headers: list<string>, rows: list<array{line: int, cells: list<string>}>}
     */
    public static function read(string $path, string $fileName): array
    {
        $extension = self::extension($fileName);

        if (!in_array($extension, self::EXTENSIONS, true)) {
            throw ImportError::make('format');
        }

        $table = $extension === 'csv' ? self::csvTable((string) @file_get_contents($path)) : self::xlsxTable($path);

        return self::fromTable($table);
    }

    public static function extension(string $fileName): string
    {
        return strtolower(pathinfo($fileName, PATHINFO_EXTENSION));
    }

    /**
     * @param list<list<mixed>> $table
     * @return array{headers: list<string>, rows: list<array{line: int, cells: list<string>}>}
     */
    public static function fromTable(array $table): array
    {
        $headers = null;
        $rows = [];

        foreach ($table as $index => $cells) {
            $cells = array_map([self::class, 'cell'], array_values((array) $cells));

            if (implode('', $cells) === '') {
                continue;
            }

            if ($headers === null) {
                $headers = $cells;
                continue;
            }

            if (count($rows) >= self::MAX_ROWS) {
                throw ImportError::make('too_many', ['max' => number_format(self::MAX_ROWS, 0, ',', '.')]);
            }

            $rows[] = ['line' => $index + 1, 'cells' => $cells];
        }

        if ($headers === null) {
            throw ImportError::make('empty');
        }

        while ($headers !== [] && end($headers) === '') {
            array_pop($headers);
        }

        return ['headers' => $headers, 'rows' => $rows];
    }

    /** @return list<list<string>> */
    public static function csvTable(string $text): array
    {
        if (str_starts_with($text, "\xEF\xBB\xBF")) {
            $text = substr($text, 3);
        }

        if (!mb_check_encoding($text, 'UTF-8')) {
            $text = (string) mb_convert_encoding($text, 'UTF-8', 'Windows-1252');
        }

        $separator = self::separator($text);
        $handle = fopen('php://temp', 'r+');

        if ($handle === false) {
            return [];
        }

        fwrite($handle, $text);
        rewind($handle);
        $table = [];

        while (($row = fgetcsv($handle, null, $separator, '"', '')) !== false) {
            $table[] = array_map('strval', array_map(static fn ($cell) => $cell ?? '', $row));
        }

        fclose($handle);

        return $table;
    }

    /** Il separatore più frequente nella prima riga, fuori dalle virgolette. */
    private static function separator(string $text): string
    {
        $line = strtok($text, "\n") ?: '';
        $line = (string) preg_replace('/"[^"]*"/', '', $line);
        $counts = [',' => substr_count($line, ','), ';' => substr_count($line, ';'), "\t" => substr_count($line, "\t")];
        arsort($counts);

        return (string) array_key_first($counts);
    }

    /** @return list<list<mixed>> */
    private static function xlsxTable(string $path): array
    {
        try {
            $reader = IOFactory::createReader('Xlsx');
            $reader->setReadDataOnly(true);
            $book = $reader->load($path);
            $table = $book->getSheet(0)->toArray(null, true, false, false);
            $book->disconnectWorksheets();
        } catch (Throwable) {
            throw ImportError::make('unreadable');
        }

        return array_values(array_map(static fn ($row): array => array_values((array) $row), $table));
    }

    /** Il testo di una cella: un EAN letto come numero resta tutte le sue cifre. */
    private static function cell(mixed $value): string
    {
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }

        if (is_float($value) && floor($value) === $value && abs($value) < 1e15) {
            return number_format($value, 0, '.', '');
        }

        return trim((string) $value);
    }
}
