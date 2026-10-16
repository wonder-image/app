<?php
/**
 * Export come testo: celle che un foglio non esegue e opzione `downloadAsText()`
 * del TableLayoutSchema.
 *
 *   php tests/Backend/Support/SpreadsheetTextTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Model;
use Wonder\App\Resource;
use Wonder\App\ResourceSchema\TableLayoutSchema;
use Wonder\Backend\Support\SpreadsheetText;

final class SpreadsheetTextModel extends Model
{
    public static string $table = 'spreadsheet_text_test';
    public static function tableSchema(): array { return []; }
    public static function dataSchema(): array { return []; }
}

final class SpreadsheetTextResource extends Resource
{
    public static string $model = SpreadsheetTextModel::class;
}

echo "SpreadsheetText\n";

check('le celle che un foglio leggerebbe come formula prendono un apice', function () {
    foreach (['=HYPERLINK("x")', '+39333', '-1', '@SUM(A1)', "\tA", "\rA"] as $cell) {
        if (SpreadsheetText::cell($cell) !== "'".$cell) {
            return false;
        }
    }
    return true;
});

check('le altre restano come sono, scritte come testo', function () {
    return SpreadsheetText::cell('Mario Rossi') === 'Mario Rossi'
        && SpreadsheetText::cell('') === ''
        && SpreadsheetText::cell(null) === ''
        && SpreadsheetText::cell(12) === '12'
        && SpreadsheetText::cell(true) === '1'
        && SpreadsheetText::cell(new DateTimeImmutable('2026-10-16 08:30:00')) === '2026-10-16 08:30:00';
});

check('la matrice tiene righe e colonne', function () {
    return SpreadsheetText::matrix([['Nome', 'Telefono'], ['Ada', '+39333'], 'sola']) === [['Nome', 'Telefono'], ['Ada', "'+39333"], ['sola']];
});

check('downloadAsText() è spento di serie e si accende sull\'export', function () {
    $off = TableLayoutSchema::for(SpreadsheetTextResource::class)->download(['csv'])->get('download');
    $on = TableLayoutSchema::for(SpreadsheetTextResource::class)->download(['csv'])->downloadAsText()->get('download');
    return $off['text'] === false && $on['text'] === true && $on['enabled'] === true;
});

check('arrayToXlsx come testo non converte telefoni e CAP in numeri', function () {
    require_once __DIR__ . '/../../../app/function/string/general.php';
    require_once __DIR__ . '/../../../app/function/arrayTo.php';
    $read = static function (bool $asText): array {
        // In CLI le intestazioni HTTP arrivano dopo l'output del test: non contano.
        set_error_handler(static fn (): bool => true, E_WARNING);
        ob_start();
        arrayToXlsx([['Telefono', 'CAP'], ['+393331234567', '00187']], 'contatti', $asText);
        restore_error_handler();
        $file = tempnam(sys_get_temp_dir(), 'xlsx');
        file_put_contents($file, (string) ob_get_clean());
        $sheet = \PhpOffice\PhpSpreadsheet\IOFactory::load($file)->getActiveSheet();
        unlink($file);
        return [$sheet->getCell('A2')->getValue(), $sheet->getCell('A2')->getDataType(), $sheet->getCell('B2')->getValue()];
    };
    return $read(true) === ['+393331234567', 's', '00187'] && $read(false)[1] === 'n';
});

summary();
