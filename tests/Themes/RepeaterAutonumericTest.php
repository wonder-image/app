<?php
/** php tests/Themes/RepeaterAutonumericTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Le righe nuove del repeater formattano i numeri come quelle già in pagina.
 *
 * Una riga aggiunta è HTML clonato da un `<template>`: senza AutoNumeric un
 * prezzo resta una casella di testo, senza « €» e senza separatori. Il
 * repeater chiama `setAutonumeric()` dopo `setInput()`, per le lib che non lo
 * fanno già dentro `setInput()`; la funzione salta i campi già avviati.
 *
 * I pezzi di JS si provano davvero in node, con un DOM finto, quando node
 * c'è; altrimenti resta la prova sul testo.
 */
$script = static function (): string {
    $field = new class {
        public array $schema = [];
    };

    $field->schema = [
        'id' => 'products',
        'name' => 'products',
        'label' => 'Quello che si vende',
        'value' => ['row_1' => ['price' => '12.5']],
        'columns' => [RepeaterColumn::key('price')->price()->label('Prezzo')->columnSpan(3)],
        'context' => ['nested' => true],
    ];

    $html = (new Repeater)->render($field);

    return substr($html, (int) strpos($html, '<script'));
};

/** Il sorgente di `window.<nome> = window.<nome> || function (...) {...};`. */
$extract = static function (string $js, string $name): string {
    $start = strpos($js, 'window.'.$name.' = window.'.$name.' || function');
    if ($start === false) { throw new RuntimeException("{$name} non trovata"); }

    $open = strpos($js, '{', $start);
    $depth = 0;

    for ($i = $open, $n = strlen($js); $i < $n; $i++) {
        if ($js[$i] === '{') { $depth++; }
        if ($js[$i] === '}' && --$depth === 0) {
            return substr($js, $start, $i - $start + 1).';';
        }
    }

    throw new RuntimeException("{$name} non chiusa");
};

/** L'eseguibile di node, se c'è. */
$node = static function (): ?string {
    $candidates = [trim((string) shell_exec('command -v node 2>/dev/null'))];
    foreach (glob((getenv('HOME') ?: '').'/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node') ?: [] as $path) {
        $candidates[] = $path;
    }
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_executable($candidate)) { return $candidate; }
    }

    return null;
};

/** Fa girare `$code` in node e torna quello che stampa, decodificato da JSON. */
$run = static function (string $code) use ($node): mixed {
    $bin = $node();
    if ($bin === null) { return null; }

    $file = tempnam(sys_get_temp_dir(), 'wirep').'.js';
    file_put_contents($file, "globalThis.window = globalThis;\n".$code);
    $out = trim((string) shell_exec(escapeshellarg($bin).' '.escapeshellarg($file).' 2>&1'));
    @unlink($file);

    $data = json_decode($out, true);
    if ($data === null) { throw new RuntimeException('node: '.$out); }

    return $data;
};

/** Un DOM minimo per `wiRepeaterAddRow`: un contenitore, un template, una riga. */
$addRowDom = <<<'JS'
var calls = [];
var row = { classList: { remove: function () {} }, setAttribute: function () {} };
var fragment = { querySelector: function () { return row; }, querySelectorAll: function () { return []; } };
var container = { querySelector: function () { return null; }, appendChild: function () { calls.push('appendChild'); } };
var template = { content: { cloneNode: function () { return fragment; } } };
globalThis.document = { getElementById: function (id) { return id === 'c' ? container : (id === 't' ? template : null); } };
JS;

check('dopo setInput la riga nuova avvia AutoNumeric', function () use ($script) {
    $js = $script();
    $setInput = strpos($js, 'window.setInput(row);');
    $autonumeric = strpos($js, "if (typeof window.setAutonumeric === 'function') window.setAutonumeric();");

    return $setInput !== false && $autonumeric !== false && $autonumeric > $setInput
        && $autonumeric < strpos($js, 'return row;');
});

check('in node: prima setInput, poi setAutonumeric, poi la riga torna', function () use ($script, $extract, $run, $addRowDom) {
    $fn = $extract($script(), 'wiRepeaterAddRow');
    $data = $run($addRowDom.$fn.<<<'JS'

window.setInput = function () { calls.push('setInput'); };
window.setAutonumeric = function () { calls.push('setAutonumeric'); };
var back = window.wiRepeaterAddRow('c', 't', 'row_2');
console.log(JSON.stringify({ calls: calls, row: back === row }));
JS);
    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data === ['calls' => ['appendChild', 'setInput', 'setAutonumeric'], 'row' => true];
});

check('in node: con una lib senza setAutonumeric la riga nasce lo stesso', function () use ($script, $extract, $run, $addRowDom) {
    $fn = $extract($script(), 'wiRepeaterAddRow');
    $data = $run($addRowDom.$fn.<<<'JS'

window.setInput = function () { calls.push('setInput'); };
var back = window.wiRepeaterAddRow('c', 't', 'row_2');
console.log(JSON.stringify({ calls: calls, row: back === row }));
JS);
    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data === ['calls' => ['appendChild', 'setInput'], 'row' => true];
});

check('in node: i numeri scritti all\'italiana, con valuta o unità, si leggono', function () use ($script, $extract, $run) {
    $fn = $extract($script(), 'wiRepeaterNumberFromText');
    $data = $run($fn.<<<'JS'

var cases = ['1.234,50 €', '12 pz', '2,500 kg', '31,50', '31.50', '€ 1.299,90', '1.234.567', '1.234.567,5',
  '1,234.50', '1,234,567', '-3,5', '0', '', 'pz', null];
console.log(JSON.stringify(cases.map(function (c) { return window.wiRepeaterNumberFromText(c); })));
JS);
    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    $expected = [1234.5, 12, 2.5, 31.5, 31.5, 1299.9, 1234567, 1234567.5, 1234.5, 1234567, -3.5, 0, null, null, null];

    foreach ($expected as $i => $value) {
        if ($data[$i] !== $value && !(is_numeric($data[$i]) && is_numeric($value) && (float) $data[$i] === (float) $value)) {
            throw new RuntimeException('caso '.$i.': '.json_encode($data[$i]).' invece di '.json_encode($value));
        }
    }

    return true;
});

check('in node: il comando di gruppo passa ad AutoNumeric il numero grezzo', function () use ($script, $extract, $run) {
    $js = $script();
    $data = $run($extract($js, 'wiRepeaterNumberFromText').$extract($js, 'wiRepeaterSetFieldValue').<<<'JS'

var set = [];
var cleared = 0;
var numeric = { set: function (v) { set.push(v); }, clear: function () { cleared++; } };
window.AutoNumeric = { getAutoNumericElement: function () { return numeric; } };
window.wiRepeaterSetFieldValue({}, '1.234,50 €');
window.wiRepeaterSetFieldValue({}, '12 pz');
window.wiRepeaterSetFieldValue({}, '');
console.log(JSON.stringify({ set: set, cleared: cleared }));
JS);
    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data['set'] == [1234.5, 12] && $data['cleared'] === 1;
});

summary();
