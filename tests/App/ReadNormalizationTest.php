<?php
/** php tests/App/ReadNormalizationTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
// sanitizeEcho() e i suoi helper same-file, senza il bootstrap completo.
require __DIR__ . '/../../app/function/string/sanitize.php';

use Wonder\App\Model;
use Wonder\Data\UploadSchema as Field;

/**
 * Model di test: dataSchema con colonne sanitize=true (text/sanitizeFirst),
 * una sanitize(false) e una JSON, per verificare che la normalizzazione in
 * lettura (inverso di sanitize) tocchi solo le colonne giuste.
 */
final class ReadNormalizationModel extends Model
{
    public static string $table = 'read_norm_test';

    public static function tableSchema(): array
    {
        return [];
    }

    public static function dataSchema(): array
    {
        return [
            Field::key('name')->text()->required()->sanitizeFirst(),
            Field::key('note')->text(),
            Field::key('raw')->text()->sanitize(false),
            Field::key('payload')->text()->json()->sanitize(false),
        ];
    }

    /** @param array<string,mixed> $row @return array<string,mixed> */
    public static function exposeNormalize(array $row): array
    {
        return static::normalizeReadRow($row);
    }

    /** @return array<int,string> */
    public static function exposeColumns(): array
    {
        return static::sanitizedReadColumns();
    }
}

$GLOBALS['CHARACTERS'] = [];

// Righe "come lette dal DB": i valori sanitize=true contengono lo slash di
// escape aggiunto in scrittura da addslashes().
$stored = [
    'name'    => "O\\'Brien",       // O\'Brien  -> O'Brien
    'note'    => "L\\'estate \\\"top\\\"", // con virgolette escapate
    'raw'     => "tieni\\'cosi",     // sanitize(false): NON toccato
    'payload' => '{"a":"b\\u0027c"}',// JSON: NON toccato
];

$out = ReadNormalizationModel::exposeNormalize($stored);
$columns = ReadNormalizationModel::exposeColumns();

check('name de-slashato in lettura', fn (): bool => $out['name'] === "O'Brien");
check('note de-slashata in lettura', fn (): bool => $out['note'] === 'L\'estate "top"');
check('raw sanitize(false) invariato', fn (): bool => $out['raw'] === "tieni\\'cosi");
check('payload JSON invariato', fn (): bool => $out['payload'] === '{"a":"b\\u0027c"}');
check('name/note nel set sanitize', fn (): bool => in_array('name', $columns, true) && in_array('note', $columns, true));
check('raw/payload fuori dal set', fn (): bool => !in_array('raw', $columns, true) && !in_array('payload', $columns, true));

summary();
