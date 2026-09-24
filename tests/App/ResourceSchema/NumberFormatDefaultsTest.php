<?php
/** php tests/App/ResourceSchema/NumberFormatDefaultsTest.php */
declare(strict_types=1);

define('APP_URL', 'https://example.test');
define('ROOT', sys_get_temp_dir());
define('ASSETS_VERSION', '1.0.0');
define('APP_VERSION', '2.2.0');

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\Inputs;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Elements\Form\Components as Elements;

/**
 * Contratto con la lib (customAutonumeric): legge dal dataset
 * wiNumberDecimal, wiNumberDecimalSeparator, wiNumberGroupSeparator,
 * wiNumberSymbol, wiNumberSymbolPlacement. Un attributo presente ma vuoto
 * arriva come '' (non undefined) e quindi vince sul preset.
 */
function attrs(object $element): array
{
    return [
        'decimal' => $element->getAttr('data-wi-number-decimal'),
        'decimal_separator' => $element->getAttr('data-wi-number-decimal-separator'),
        'group_separator' => $element->getAttr('data-wi-number-group-separator'),
        'symbol' => $element->getAttr('data-wi-number-symbol'),
        'symbol_placement' => $element->getAttr('data-wi-number-symbol-placement'),
    ];
}

function hasAttr(object $element, string $name): bool
{
    return array_key_exists($name, (array) $element->getSchema('attributes'));
}

# ---------------------------------------------------------------------------
# Default italiani sugli Element
# ---------------------------------------------------------------------------

check('numero: virgola decimale e nessun separatore delle migliaia', function () {
    $a = attrs(new Elements\InputNumber('q'));

    return $a['decimal_separator'] === ',' && $a['group_separator'] === '';
});

check('percentuale: stessi default del numero', function () {
    $el = new Elements\InputPercentige('p');
    $a = attrs($el);

    return $a['decimal_separator'] === ',' && $a['group_separator'] === ''
        && $el->getAttr('data-wi-percentige') === 'true'
        && !hasAttr($el, 'data-wi-number');
});

check('prezzo: virgola, punto delle migliaia e " €" in coda', function () {
    $a = attrs(new Elements\InputPrice('p'));

    return $a['decimal_separator'] === ','
        && $a['group_separator'] === '.'
        && $a['symbol'] === ' €'
        && $a['symbol_placement'] === 's';
});

check('prezzo: solo data-wi-price, niente data-wi-percentige né data-wi-number', function () {
    $el = new Elements\InputPrice('p');

    return $el->getAttr('data-wi-price') === 'true'
        && !hasAttr($el, 'data-wi-percentige')
        && !hasAttr($el, 'data-wi-number');
});

check('prezzo non è più una percentuale', function () {
    return !(new Elements\InputPrice('p') instanceof Elements\InputPercentige)
        && new Elements\InputPrice('p') instanceof Elements\InputNumber;
});

check('la config esplicita sull\'Element vince sul default', function () {
    $a = attrs((new Elements\InputPrice('p'))->decimalSeparator('.')->groupSeparator(',')->symbol('$')->symbolPlacement('p'));

    return $a['decimal_separator'] === '.' && $a['group_separator'] === ','
        && $a['symbol'] === '$' && $a['symbol_placement'] === 'p';
});

# ---------------------------------------------------------------------------
# Resa HTML: il separatore vuoto arriva come attributo vuoto, lo spazio resta
# ---------------------------------------------------------------------------

foreach (['wonder', 'bootstrap'] as $theme) {
    check("numero [{$theme}]: data-wi-number-group-separator=\"\" nel markup", function () use ($theme) {
        $html = FormField::key('q')->number()->render($theme);

        return str_contains($html, 'data-wi-number-group-separator=""')
            && str_contains($html, 'data-wi-number-decimal-separator=","');
    });

    check("prezzo [{$theme}]: \" €\" con lo spazio davanti e niente percentuale", function () use ($theme) {
        $html = FormField::key('p')->price()->render($theme);

        return str_contains($html, 'data-wi-number-symbol=" €"')
            && str_contains($html, 'data-wi-number-group-separator="."')
            && str_contains($html, 'data-wi-price="true"')
            && !str_contains($html, 'data-wi-percentige');
    });

    check("percentuale [{$theme}]: default italiani nel markup", function () use ($theme) {
        $html = FormField::key('p')->percentige()->render($theme);

        return str_contains($html, 'data-wi-percentige="true"')
            && str_contains($html, 'data-wi-number-decimal-separator=","');
    });
}

# ---------------------------------------------------------------------------
# integer() e suffix() sullo schema
# ---------------------------------------------------------------------------

check('integer() equivale a decimal(0)', function () {
    $field = FormField::key('q')->number()->integer();

    return ($field->get('context')['number'] ?? null) === ['decimal' => 0]
        && (string) $field->compile()->getAttr('data-wi-number-decimal') === '0';
});

check('integer() c\'è anche sul tipo diretto', function () {
    return (string) Inputs\InputNumber::key('q')->integer()->compile()->getAttr('data-wi-number-decimal') === '0';
});

check('suffix() mette il simbolo in coda', function () {
    $field = FormField::key('q')->number()->suffix(' kg');
    $a = attrs($field->compile());

    return ($field->get('context')['number'] ?? null) === ['symbol' => ' kg', 'symbol_placement' => 's']
        && $a['symbol'] === ' kg' && $a['symbol_placement'] === 's';
});

check('suffix() e integer() si concatenano', function () {
    $a = attrs(FormField::key('q')->number()->integer()->suffix(' pz')->compile());

    return (string) $a['decimal'] === '0' && $a['symbol'] === ' pz' && $a['symbol_placement'] === 's';
});

# ---------------------------------------------------------------------------
# Config esplicita dallo schema: vince sempre, anche quando è vuota
# ---------------------------------------------------------------------------

check('prezzo senza separatore delle migliaia: groupSeparator(\'\') arriva vuoto', function () {
    return attrs(FormField::key('p')->price()->groupSeparator('')->compile())['group_separator'] === '';
});

check('prezzo senza simbolo: symbol(\'\') arriva vuoto', function () {
    return attrs(FormField::key('p')->price()->symbol('')->compile())['symbol'] === '';
});

check('numero con separatore decimale esplicito', function () {
    $a = attrs(FormField::key('q')->number()->decimalSeparator('.')->groupSeparator(' ')->compile());

    return $a['decimal_separator'] === '.' && $a['group_separator'] === ' ';
});

check('decimalSeparator(\'\') è ignorato: resta la virgola', function () {
    return attrs(FormField::key('q')->number()->decimalSeparator('')->compile())['decimal_separator'] === ',';
});

check('prezzo con simbolo esplicito in testa', function () {
    $a = attrs(FormField::key('p')->price()->symbol('$ ')->symbolPlacement('p')->compile());

    return $a['symbol'] === '$ ' && $a['symbol_placement'] === 'p';
});

# ---------------------------------------------------------------------------
# RepeaterColumn prende gli stessi default
# ---------------------------------------------------------------------------

check('RepeaterColumn price: default italiani', function () {
    $a = attrs(RepeaterColumn::key('p')->price()->compile());

    return $a['decimal_separator'] === ',' && $a['group_separator'] === '.' && $a['symbol'] === ' €';
});

check('RepeaterColumn number: default italiani e integer()', function () {
    $a = attrs(RepeaterColumn::key('q')->number()->integer()->compile());

    return $a['decimal_separator'] === ',' && $a['group_separator'] === '' && (string) $a['decimal'] === '0';
});

summary();
