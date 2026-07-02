<?php
/** php tests/Backend/Table/FieldBadgeTest.php */
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

$expectedOn = "<span class='badge text-bg-success'><span class='pc-none'><i class='bi bi-check-circle'></i></span><span class='phone-none'>ABILITATO</span></span>";

// descrittore preset: render col contesto iniettato, nessun global letto
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false]]
);
eq('preset badge renders', $got, $expectedOn);

// non cliccabile di default: nessun onclick nell'output
eq('no onclick by default', str_contains((string) $got, 'onclick'), false);

// clickable forzato: onclick con table e id iniettati (endpoint change/boolean)
$field = makeField();
$got = $field->newField(
    ['id' => 7, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => true]]
);
eq('clickable has onclick', str_contains((string) $got, 'onclick'), true);
eq('clickable url has table', str_contains((string) $got, 'table=event'), true);
eq('clickable url has id', str_contains((string) $got, 'id=7'), true);
eq('clickable url has column', str_contains((string) $got, 'column=active'), true);

// badge generico con on/off custom
$field = makeField();
$got = $field->newField(
    ['id' => 3, 'stato' => 'false'],
    'stato',
    ['format' => 'badge', 'badge' => [
        'preset' => null, 'column' => 'stato', 'variant' => 'badge', 'clickable' => false,
        'on' => ['text' => 'Aperto', 'icon' => '', 'color' => 'success', 'button' => ''],
        'off' => ['text' => 'Chiuso', 'icon' => '', 'color' => 'danger', 'button' => ''],
    ]]
);
eq('generic badge off', $got, "<span class='badge text-bg-danger'>CHIUSO</span>");

// descrittore malformato: cella vuota, nessun errore
$field = makeField();
$got = $field->newField(
    ['id' => 3, 'active' => 'true'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'ghost', 'column' => 'active']]
);
eq('unknown preset renders empty', $got, '');

// special-case tabella user: badge nascosto per utenti multi-area+authority
$field = makeField('user');
$got = $field->newField(
    ['id' => 2, 'active' => 'true', 'area' => '["a","b"]', 'authority' => '["x","y"]'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false]]
);
eq('user multi area+authority hidden', $got, '');

$field = makeField('user');
$got = $field->newField(
    ['id' => 2, 'active' => 'true', 'area' => '["a"]', 'authority' => '["x","y"]'],
    'active',
    ['format' => 'badge', 'badge' => ['preset' => 'active', 'column' => 'active', 'variant' => 'automaticResize', 'clickable' => false]]
);
eq('user single area shown', $got, $expectedOn);

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
