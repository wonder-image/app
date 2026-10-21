<?php
/**
 * CSV neutralizzato di serie contro le formule (C-UPL-4).
 *
 *   php tests/Backend/Support/CsvFormulaSafeTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';
require __DIR__ . '/../../../app/function/string/general.php';
require __DIR__ . '/../../../app/function/arrayTo.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Support\ResourceDownloadController;
use Wonder\Backend\Support\SpreadsheetText;

final class CsvFormulaSafeModel extends Model
{
    public static string $table = 'csv_formula_safe_test';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class CsvFormulaSafeResource extends Resource
{
    public static string $model = CsvFormulaSafeModel::class;
}

/** Righe del CSV scritto su php://output, rilette con fgetcsv. */
function csvRows(callable $write): array
{
    // In CLI header() non invia nulla; eventuali avvisi non contano qui.
    set_error_handler(static fn (): bool => true, E_WARNING);
    ob_start();
    $write();
    $csv = (string) ob_get_clean();
    restore_error_handler();
    $handle = fopen('php://memory', 'r+');
    fwrite($handle, $csv);
    rewind($handle);
    $rows = [];
    while (($row = fgetcsv($handle, null, ',', '"', '\\')) !== false) {
        $rows[] = $row;
    }
    fclose($handle);
    return $rows;
}

$matrix = [
    ['Formula', 'Negativo', 'Chiocciola', 'Testo'],
    ['=1+1', '-5', '@x', 'Mario Rossi'],
    ['+39 333 1234567', '+39', '-', "\tA"],
    ['-A1+B1', '3.5', '', 'a=b'],
];

check('arrayToCsv di serie: =1+1 e @x neutralizzati, -5 e testo invariati', function () use ($matrix) {
    $rows = csvRows(static fn () => arrayToCsv($matrix, 'export'));
    return $rows[0] === $matrix[0]
        && $rows[1] === ["'=1+1", '-5', "'@x", 'Mario Rossi']
        && $rows[2] === ["'+39 333 1234567", '+39', '-', "'\tA"]
        && $rows[3] === ["'-A1+B1", '3.5', '', 'a=b'];
});

check('opt-out esplicito: arrayToCsv(..., false) scrive i valori grezzi', function () use ($matrix) {
    return csvRows(static fn () => arrayToCsv($matrix, 'export', false)) === $matrix;
});

check('formulaSafe lascia invariati numeri, null e valori non stringa', function () {
    return SpreadsheetText::formulaSafe(-5) === -5
        && SpreadsheetText::formulaSafe('+39') === '+39'
        && SpreadsheetText::formulaSafe('-1e3') === '-1e3'
        && SpreadsheetText::formulaSafe(null) === null
        && SpreadsheetText::formulaSafe('@') === '@'
        && SpreadsheetText::formulaSafe("'=già") === "'=già"
        && SpreadsheetText::formulaSafe('=SUM(A1)') === "'=SUM(A1)";
});

check('download della Resource: formula_safe acceso di serie, si spegne a mano', function () {
    $on = TableLayoutSchema::for(CsvFormulaSafeResource::class)->download(['csv'])->get('download');
    $off = TableLayoutSchema::for(CsvFormulaSafeResource::class)->download(['csv'])->downloadFormulaSafe(false)->get('download');
    return $on['formula_safe'] === true && $on['text'] === false && $off['formula_safe'] === false;
});

check('controller: CSV neutralizzato di serie, grezzo con l\'opt-out, testo invariato', function () use ($matrix) {
    $controller = (new ReflectionClass(ResourceDownloadController::class))->newInstanceWithoutConstructor();
    $dispatch = new ReflectionMethod(ResourceDownloadController::class, 'dispatch');
    $safe = csvRows(static fn () => $dispatch->invoke($controller, 'csv', $matrix, 'x', false, true));
    $raw = csvRows(static fn () => $dispatch->invoke($controller, 'csv', $matrix, 'x', false, false));
    $text = csvRows(static fn () => $dispatch->invoke($controller, 'csv', $matrix, 'x', true, true));
    return $safe[1] === ["'=1+1", '-5', "'@x", 'Mario Rossi']
        && $raw === $matrix
        && $text[1] === ["'=1+1", "'-5", "'@x", 'Mario Rossi'];
});

check('sqlExport passa il default (neutralizzato) ad arrayToCsv', function () {
    require_once __DIR__ . '/../../../app/function/sql.php';
    $parameter = (new ReflectionFunction('sqlExport'))->getParameters()[2] ?? null;
    $source = (string) file_get_contents(__DIR__ . '/../../../app/function/sql.php');
    return $parameter !== null && $parameter->getDefaultValue() === true
        && str_contains($source, 'arrayToCsv($ARRAY, $table, $formulaSafe)');
});

summary();
