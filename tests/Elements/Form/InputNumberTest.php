<?php
/** php tests/Elements/Form/InputNumberTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.2.0');

require __DIR__ . '/../../../vendor/autoload.php';

use Wonder\App\Theme;
use Wonder\Elements\Form\Components\InputNumber;

Theme::set('wonder');

$fail = 0;
function has(string $label, string $html, string $needle): void {
    global $fail;
    if (str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  missing: $needle\n  in: $html\n"; }
}
function hasnt(string $label, string $html, string $needle): void {
    global $fail;
    if (!str_contains($html, $needle)) { echo "ok: $label\n"; }
    else { $fail++; echo "FAIL: $label\n  unexpected: $needle\n  in: $html\n"; }
}

// Contratto attributi: il JS di formatting (wonder-image/lib >= 2.0) legge via
// element.dataset.wiNumber* -> quindi ogni override per-campo DEVE renderizzare
// come attributo `data-wi-number-*`. Verificato in dist/frontend/head.js
// (funzione customAutonumeric) sulle lib 2.0.1 / 2.1.0 / 2.1.1-alpha.3.
$el = (new InputNumber('price'))
    ->decimal(2)
    ->decimalSeparator(',')
    ->groupSeparator('.')
    ->symbol('€')
    ->symbolPlacement('s');

$html = $el->render();

has('data-wi-number attivatore', $html, 'data-wi-number="true"');
has('decimal -> data-wi-number-decimal', $html, 'data-wi-number-decimal="2"');
has('decimalSeparator -> data-wi-number-decimal-separator', $html, 'data-wi-number-decimal-separator=","');
has('groupSeparator -> data-wi-number-group-separator', $html, 'data-wi-number-group-separator="."');
has('symbol -> data-wi-number-symbol', $html, 'data-wi-number-symbol="€"');
has('symbolPlacement -> data-wi-number-symbol-placement', $html, 'data-wi-number-symbol-placement="s"');

// Regressione bug copia-incolla: symbolPlacement NON deve sovrascrivere il
// simbolo scrivendo il placement dentro data-wi-number-symbol.
hasnt('placement non clobbera il simbolo', $html, 'data-wi-number-symbol="s"');

// Regressione prefisso mancante: nessun attributo bare `wi-number-*` (senza
// data-) deve finire nel markup, altrimenti dataset non lo legge.
hasnt('nessun bare wi-number-decimal', $html, ' wi-number-decimal=');
hasnt('nessun bare wi-number-decimal-separator', $html, ' wi-number-decimal-separator=');
hasnt('nessun bare wi-number-group-separator', $html, ' wi-number-group-separator=');
hasnt('nessun bare wi-number-symbol', $html, ' wi-number-symbol=');

// Placement 'p' (prefix) funziona come 's'.
$prefix = (new InputNumber('price'))->symbol('$')->symbolPlacement('p')->render();
has('placement p', $prefix, 'data-wi-number-symbol-placement="p"');
has('symbol con placement p intatto', $prefix, 'data-wi-number-symbol="$"');

// Il contratto di validazione del placement resta invariato.
$threw = false;
try {
    (new InputNumber('price'))->symbolPlacement('x');
} catch (\Throwable $e) {
    $threw = true;
}
if ($threw) { echo "ok: symbolPlacement invalido lancia\n"; }
else { $fail++; echo "FAIL: symbolPlacement invalido lancia\n"; }

echo $fail === 0 ? "\nTutti i test passati\n" : "\n$fail test falliti\n";
exit($fail === 0 ? 0 : 1);
