<?php
/** php tests/Backend/Table/FieldBadgeLegacyRemapTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\Backend\Table\Field;

$fail = 0;
function eq(string $label, $got, $expected) {
    global $fail;
    $g = json_encode($got); $e = json_encode($expected);
    if ($g !== $e) { $fail++; echo "FAIL: $label\n  expected: $e\n  got:      $g\n"; }
    else { echo "ok: $label\n"; }
}

function makeField(string $tableName = 'event'): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => $tableName, 'connection' => null, 'database' => 'main',
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

// Nessun global $NAME/$PATH definito: il percorso legacy deve comunque
// renderizzare senza warning. Convertiamo i warning in eccezioni per
// intercettare regressioni.
set_error_handler(function ($severity, $message, $file, $line) {
    throw new ErrorException($message, 0, $severity, $file, $line);
});

$expectedOn = "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>";

// il caso esatto del bug: TableColumn::key('active')->badge()->function('active', 'id', 'automaticResize')
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'function' => ['name' => 'active', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy active remapped, no warning', $got, $expectedOn);
eq('legacy remap not clickable', str_contains((string) $got, 'onclick'), false);

// visible ed evidence
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'visible' => 'false'],
    'visible',
    ['format' => 'badge', 'function' => ['name' => 'visible', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy visible remapped', $got,
    "<span class='badge text-bg-danger'><span class='pc-none'><i class='bi bi-eye-slash'></i></span><span class='phone-none'>NASCOSTO</span></span>"
);

$field = makeField();
$got = $field->newField(
    ['id' => 7, 'evidence' => 'false'],
    'evidence',
    ['format' => 'badge', 'function' => ['name' => 'evidence', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy evidence off remapped empty', $got, '');

// return mancante → default automaticResize
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'function' => ['name' => 'active', 'parameter' => 'id', 'return' => null]]
);
eq('legacy remap default variant', $got, $expectedOn);

// special-case user preservata anche sul percorso legacy
$field = makeField('user');
$got = $field->newField(
    ['id' => 2, 'active' => 'true', 'area' => '["a","b"]', 'authority' => '["x","y"]'],
    'active',
    ['format' => 'badge', 'function' => ['name' => 'active', 'parameter' => 'id', 'return' => 'automaticResize']]
);
eq('legacy remap user gate', $got, '');

restore_error_handler();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
