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

function makeField(array $links = []): Field {
    $TABLE = (object) [
        'id' => 'tbl-1', 'table' => 'immobili', 'connection' => null, 'database' => 'main',
        'field' => [], 'page' => 0, 'length' => 10, 'link' => $links,
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

// Colonne immagine: il formatter fornisce la SORGENTE (src), non l'intera
// cella; il framework la avvolge nel medesimo <img> del tipo image nativo.
$imgTag = static fn (string $src): string =>
    "<img src='".htmlspecialchars($src, ENT_QUOTES)."' class='img-thumbnail object-fit-contain' style='max-width: calc(((61.5px - 1rem) / 2) * 3) !important;width: calc(((61.5px - 1rem) / 2) * 3) !important; height: calc(61.5px - 1rem) !important;'>";

ColumnFormatterRegistry::register('immobili.image', static fn (array $row): string =>
    (string) ($row['cover'] ?? ''));

$field = makeField();
$got = $field->newField(
    ['id' => 7, 'cover' => 'https://cdn.example.com/immobili/7-620.webp'],
    'image',
    ['format' => 'image', 'formatter' => 'immobili.image']
);
eq('image + formatter -> <img> con src dal formatter', $got, $imgTag('https://cdn.example.com/immobili/7-620.webp'));

// formatter che ritorna '' -> cella vuota, nessun <img> rotto
$field = makeField();
$got = $field->newField(
    ['id' => 8],
    'image',
    ['format' => 'image', 'formatter' => 'immobili.image']
);
eq('image + formatter vuoto -> cella vuota', $got, '');

// src con carattere pericoloso (apice/&) -> escaped nell'attributo
$field = makeField();
$got = $field->newField(
    ['id' => 9, 'cover' => "https://cdn.example.com/a'b?x=1&y=2.webp"],
    'image',
    ['format' => 'image', 'formatter' => 'immobili.image']
);
eq('image + formatter -> src escaped', $got, $imgTag("https://cdn.example.com/a'b?x=1&y=2.webp"));

// formatter + link('view'): l'output del formatter viene avvolto nel <a href>
$field = makeField(['view' => '/backend/immobili/{rowId}/view']);
$got = $field->newField(
    ['id' => 5, 'prezzo' => 255000, 'contratto_id' => 'V'],
    'prezzo',
    ['format' => 'text', 'formatter' => 'immobili.prezzo', 'href' => 'view']
);
eq('formatter + href=view -> output avvolto nel link', $got,
    "<a href='/backend/immobili/5/view' class='fw-semibold text-dark'>€ 255.000</a>");

// image + link('view'): l'<img> viene avvolta nel <a href>
$field = makeField(['view' => '/backend/immobili/{rowId}/view']);
$got = $field->newField(
    ['id' => 7, 'cover' => 'https://cdn.example.com/immobili/7-620.webp'],
    'image',
    ['format' => 'image', 'formatter' => 'immobili.image', 'href' => 'view']
);
eq('image + href=view -> <img> avvolta nel link', $got,
    "<a href='/backend/immobili/7/view' class='fw-semibold text-dark'>".$imgTag('https://cdn.example.com/immobili/7-620.webp')."</a>");

// image + link ma copertina vuota: cella vuota, nessun link attorno al nulla
$field = makeField(['view' => '/backend/immobili/{rowId}/view']);
$got = $field->newField(
    ['id' => 8],
    'image',
    ['format' => 'image', 'formatter' => 'immobili.image', 'href' => 'view']
);
eq('image + href=view ma vuoto -> cella vuota', $got, '');

ColumnFormatterRegistry::reset();

echo $fail === 0 ? "\nALL PASS\n" : "\n$fail FAILURES\n";
exit($fail === 0 ? 0 : 1);
