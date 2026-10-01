<?php
/** php tests/Backend/Table/FieldMoneyTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\ResourceSchema\TableColumn;
use Wonder\Backend\Table\Field;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'ordini', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'ordine', 'titleP' => 'ordini', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

$span = static fn (string $text): string =>
    '<span class="d-block text-end" style="font-variant-numeric: tabular-nums">'.$text.'</span>';

$colonna = TableColumn::key('total')->money()->toArray();
eq('money() imposta il tipo della colonna', $colonna['type'] ?? null, 'money');

$format = ['format' => 'money'];

eq('un importo si scrive all\'italiana, a destra, con cifre tabulari',
    makeField()->newField(['id' => 1, 'total' => '1234.5'], 'total', $format),
    $span('1.234,50 €'));

eq('lo zero resta uno zero: 0,00 €',
    makeField()->newField(['id' => 1, 'total' => '0.00'], 'total', $format),
    $span('0,00 €'));

eq('un importo negativo mantiene il segno',
    makeField()->newField(['id' => 1, 'total' => '-12.5'], 'total', $format),
    $span('-12,50 €'));

eq('il valore mancante lascia la cella vuota',
    makeField()->newField(['id' => 1, 'total' => null], 'total', $format),
    '');

eq('un valore non numerico non si scrive: cella vuota',
    makeField()->newField(['id' => 1, 'total' => '<b>x</b>'], 'total', $format),
    '');

exit($fail > 0 ? 1 : 0);
