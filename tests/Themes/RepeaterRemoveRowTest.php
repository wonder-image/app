<?php
/** php tests/Themes/RepeaterRemoveRowTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Themes\Bootstrap\Form\Components\Repeater;

/**
 * Svuotare l'ultima riga deve farsi sentire: la barra di salvataggio e check()
 * ascoltano `change`. I pezzi di JS girano in node con un DOM finto quando
 * node c'e; altrimenti resta la prova sul testo.
 */
$field = new class {
    public array $schema = [];
};

$field->schema = [
    'id' => 'products',
    'name' => 'products',
    'label' => 'Prodotti',
    'value' => ['row_1' => ['price' => '12.5']],
    'columns' => [RepeaterColumn::key('price')->price()->label('Prezzo')->columnSpan(3)],
    'context' => ['nested' => true],
];

$html = (new Repeater)->render($field);
$js = substr($html, (int) strpos($html, '<script'));

// Il sorgente di `window.<nome> = window.<nome> || function (...) {...};`.
$extract = static function (string $name) use ($js): string {
    $start = strpos($js, 'window.'.$name.' = window.'.$name.' || function');
    if ($start === false) { throw new RuntimeException("{$name} non trovata"); }

    $depth = 0;

    for ($i = strpos($js, '{', $start), $n = strlen($js); $i < $n; $i++) {
        if ($js[$i] === '{') { $depth++; }
        if ($js[$i] === '}' && --$depth === 0) {
            return substr($js, $start, $i - $start + 1).';';
        }
    }

    throw new RuntimeException("{$name} non chiusa");
};

// Esegue il codice in node e decodifica il JSON stampato; null senza node.
$run = static function (string $code): mixed {
    $candidates = [trim((string) shell_exec('command -v node 2>/dev/null'))];
    foreach (glob((getenv('HOME') ?: '').'/Library/Application Support/Herd/config/nvm/versions/node/*/bin/node') ?: [] as $path) {
        $candidates[] = $path;
    }

    $bin = null;
    foreach ($candidates as $candidate) {
        if ($candidate !== '' && is_executable($candidate)) { $bin = $candidate; break; }
    }
    if ($bin === null) { return null; }

    $file = tempnam(sys_get_temp_dir(), 'wirep').'.js';
    file_put_contents($file, "globalThis.window = globalThis;\n".$code);
    $out = trim((string) shell_exec(escapeshellarg($bin).' '.escapeshellarg($file).' 2>&1'));
    @unlink($file);

    $data = json_decode($out, true);
    if ($data === null) { throw new RuntimeException('node: '.$out); }

    return $data;
};

// Una riga con checkbox, radio, testo, textarea, select e un prezzo AutoNumeric.
$dom = <<<'JS'
var events = [];
var setCalls = [];
var cleared = 0;
var removed = 0;
window.wiRepeaterConfirmDelete = function (onConfirm) { onConfirm(); };
window.AutoNumeric = { getAutoNumericElement: function (field) { return field.numeric || null; } };
var setFieldValue = window.wiRepeaterSetFieldValue;
window.wiRepeaterSetFieldValue = function (field, value) { setCalls.push(field.name + '=' + value); setFieldValue(field, value); };

function remove(visibleRows) {
  var fields = ['checkbox', 'radio', 'text', 'textarea', 'select-one', 'price'].map(function (type) {
    return {
      name: type,
      type: type === 'price' ? 'text' : type,
      value: 'x',
      checked: true,
      numeric: type === 'price' ? { clear: function () { cleared++; }, set: function () {} } : null,
      dispatchEvent: function (event) { events.push(type + ':' + event.type + ':' + event.bubbles); }
    };
  });
  var container = { dataset: {}, querySelectorAll: function () { return new Array(visibleRows); } };
  var row = {
    parentElement: container,
    querySelector: function () { return null; },
    querySelectorAll: function () { return fields; },
    remove: function () { removed++; }
  };
  window.wiRepeaterRemoveRow({ closest: function () { return row; }, getAttribute: function () { return null; } });

  return fields.map(function (field) { return field.type === 'checkbox' || field.type === 'radio' ? field.checked : field.value; });
}
JS;

check('P14 svuotare l\'ultima riga: change con bubbles su ogni campo, testo via wiRepeaterSetFieldValue', function () use ($extract, $run, $dom) {
    $code = $extract('wiRepeaterNumberFromText').$extract('wiRepeaterSetFieldValue').$extract('wiRepeaterRemoveRow');
    $data = $run($code.$dom.<<<'JS'

var values = remove(1);
console.log(JSON.stringify({ values: values, events: events, setCalls: setCalls, cleared: cleared, removed: removed }));
JS);

    if ($data === null) {
        echo "    (node non trovato: prova sul testo)\n";
        $remove = $extract('wiRepeaterRemoveRow');

        return str_contains($remove, "input.checked = false;\n                        input.dispatchEvent(new Event('change', { bubbles: true }));")
            && str_contains($remove, "window.wiRepeaterSetFieldValue(input, '');")
            && !str_contains($remove, "input.value = '';");
    }

    return $data === [
        'values' => [false, false, '', '', '', 'x'],
        'events' => [
            'checkbox:change:true',
            'radio:change:true',
            'text:input:true', 'text:change:true',
            'textarea:input:true', 'textarea:change:true',
            'select-one:input:true', 'select-one:change:true',
            'price:change:true',
        ],
        'setCalls' => ['text=', 'textarea=', 'select-one=', 'price='],
        'cleared' => 1,
        'removed' => 0,
    ];
});

check('P14 togliere una riga che non e l\'ultima: nessun change in piu', function () use ($extract, $run, $dom) {
    $code = $extract('wiRepeaterNumberFromText').$extract('wiRepeaterSetFieldValue').$extract('wiRepeaterRemoveRow');
    $data = $run($code.$dom.<<<'JS'

var values = remove(2);
console.log(JSON.stringify({ values: values, events: events, setCalls: setCalls, removed: removed }));
JS);

    if ($data === null) { echo "    (node non trovato: prova saltata)\n"; return true; }

    return $data === [
        'values' => [true, true, 'x', 'x', 'x', 'x'],
        'events' => [],
        'setCalls' => [],
        'removed' => 1,
    ];
});

check('P14 il ramo AutoNumeric di wiRepeaterSetFieldValue emette change con bubbles', function () use ($extract, $run) {
    $code = $extract('wiRepeaterNumberFromText').$extract('wiRepeaterSetFieldValue');
    $data = $run($code.<<<'JS'

var events = [];
var numeric = { set: function () {}, clear: function () {} };
var field = { dispatchEvent: function (event) { events.push(event.type + ':' + event.bubbles); } };
window.AutoNumeric = { getAutoNumericElement: function () { return numeric; } };
window.wiRepeaterSetFieldValue(field, '12,50');
window.wiRepeaterSetFieldValue(field, '');
console.log(JSON.stringify(events));
JS);

    if ($data === null) {
        echo "    (node non trovato: prova sul testo)\n";

        return str_contains($extract('wiRepeaterSetFieldValue'), "numeric.set(raw);\n        }\n\n        field.dispatchEvent(new Event('change', { bubbles: true }));");
    }

    return $data === ['change:true', 'change:true'];
});

summary();
