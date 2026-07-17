<?php
/** php tests/Backend/Table/FieldFormatterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\ColumnFormatterRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'immobili', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'immobile', 'titleP' => 'immobili', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

// Funzione globale reale usata come "canary" di sicurezza: esiste
// (function_exists === true) ma NON è registrata nel ColumnFormatterRegistry.
function wi_test_formatter_canary(array $row): string { return 'CANARY-ESEGUITA'; }

ColumnFormatterRegistry::reset();
ColumnFormatterRegistry::register('immobili.prezzo', static fn (array $row): string =>
    '€ ' . number_format((int) ($row['prezzo'] ?? 0), 0, ',', '.'));

// formatter registrato: la cella usa il suo output, riceve tutta la riga
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'prezzo' => 255000, 'contratto_id' => 'V'],
    'prezzo',
    ['format' => 'text', 'formatter' => 'immobili.prezzo']
);
eq('formatter registrato rende la cella', $got, '€ 255.000');

// formatter NON registrato: cella vuota, nessuna esecuzione
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'prezzo' => 255000],
    'prezzo',
    ['format' => 'text', 'formatter' => 'immobili.non_registrato']
);
eq('formatter non registrato -> vuoto', $got, '');

// Sicurezza (difesa a prova di refactoring): un nome che coincide con una
// funzione globale REALE ma non registrata non deve essere invocato. Se un
// giorno il dispatch acquisisse un fall-through function_exists()/call_user_func,
// l'output sarebbe 'CANARY-ESEGUITA' e questo test fallirebbe.
$field = makeField();
$got = $field->newField(
    ['id' => 5],
    'prezzo',
    ['format' => 'text', 'formatter' => 'wi_test_formatter_canary']
);
eq('funzione globale non registrata NON invocata come formatter', $got, '');

ColumnFormatterRegistry::reset();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
