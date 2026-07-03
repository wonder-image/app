<?php
/** php tests/Backend/Table/FieldFunctionWhitelistTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;
use Wonder\Backend\Table\ColumnFunctionRegistry;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'event', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => [],
    ];
    $PATH = (object) [ 'site' => '', 'backend' => '/backend', 'app' => '/app', 'api' => '/api' ];
    $TEXT = (object) [
        'titleS' => 'evento', 'titleP' => 'eventi', 'last' => 'ultimi', 'all' => 'tutti',
        'article' => 'gli', 'full' => 'pieno', 'empty' => 'vuoto', 'this' => 'questo',
    ];
    $USER = (object) [ 'area' => '', 'authority' => '' ];
    $PAGE = (object) [ 'redirect' => '', 'redirectBase64' => '' ];
    return new Field($TABLE, $PATH, $TEXT, $USER, $PAGE);
}

// funzione "di sito" legittima, registrata esplicitamente
function wi_test_column_fn($v) { return "X-$v"; }

ColumnFunctionRegistry::reset();

// nome NON in whitelist (una funzione PHP qualunque): cella vuota, nessuna esecuzione
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => 'strtoupper', 'parameter' => 'name', 'return' => null]]
);
eq('non-whitelisted function blocked', $got, '');

// stesso nome dopo allow(): eseguita
ColumnFunctionRegistry::allow('wi_test_column_fn');
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => 'wi_test_column_fn', 'parameter' => 'name', 'return' => null]]
);
eq('whitelisted function runs', $got, 'X-hello');

// nome in whitelist ma funzione inesistente: cella vuota, nessun fatal
ColumnFunctionRegistry::allow('wi_test_missing_fn');
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => 'wi_test_missing_fn', 'parameter' => 'name', 'return' => null]]
);
eq('missing function renders empty', $got, '');

// function.name non-stringa (es. array malformato dal POST): cella vuota,
// nessun TypeError. ColumnFunctionRegistry::isAllowed()/function_exists()
// richiedono una stringa: senza guard is_string, un array qui produce un
// TypeError fatale (500) invece di una cella vuota.
$field = makeField();
$got = $field->newField(
    ['id' => 5, 'name' => 'hello'],
    'name',
    ['format' => 'text', 'function' => ['name' => ['x'], 'parameter' => 'name', 'return' => null]]
);
eq('non-string function name renders empty without error', $got, '');

ColumnFunctionRegistry::reset();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
