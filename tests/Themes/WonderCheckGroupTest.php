<?php
/**
 * CheckGroup nel tema Wonder: lista e pillole, segno dell'opzione, valore
 * scelto e CSS una volta per pagina.
 *
 *   php tests/Themes/WonderCheckGroupTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Form\Components\CheckGroup;
use Wonder\Themes\Support\PageAssets;

echo "WonderCheckGroup\n";

$colors = static fn (): CheckGroup => (new CheckGroup('colors'))
    ->label('Colori')
    ->options([
        'red' => ['name' => 'Rosso', 'color' => '#c00'],
        'blue' => ['name' => 'Blu', 'color' => '#06c'],
        'water' => ['name' => 'Impermeabile', 'icon' => 'bi-droplet'],
        'plain' => 'Tinta unita',
    ])
    ->pills()
    ->value(['red']);

check('pills(): una pillola per voce, con input e label fratelli', function () use ($colors) {
    PageAssets::reset();
    $html = $colors()->render('wonder');

    return substr_count($html, 'class="wi-check-pill"') === 4
        && substr_count($html, 'class="wi-check-pill-input"') === 4
        && substr_count($html, 'class="wi-check-pill-label unselectable"') === 4
        && str_contains($html, 'name="colors[]"')
        && !str_contains($html, 'wi-checkbox-list');
});

check('pills(): il segno sta davanti al nome (colore, icona), nessuno per una stringa', function () use ($colors) {
    PageAssets::reset();
    $html = $colors()->render('wonder');

    return str_contains($html, 'style="color:#c00"')
        && str_contains($html, 'bi-droplet wi-option-visual')
        && str_contains($html, '</i> Rosso</label>')
        && str_contains($html, '>Tinta unita</label>');
});

check('pills(): solo la voce in value() è spuntata', function () use ($colors) {
    PageAssets::reset();
    $html = $colors()->render('wonder');

    return substr_count($html, ' checked') === 1
        && preg_match('/value="red"[^>]* checked/', $html) === 1;
});

check('pills() con le radio: nome senza [] e valore singolo', function () {
    PageAssets::reset();
    $html = (new CheckGroup('preferred'))
        ->inputType('radio')
        ->options(['7' => 'Preferito', '9' => 'Riserva'])
        ->pills()
        ->label('')
        ->value('7')
        ->render('wonder');

    return str_contains($html, 'type="radio"')
        && str_contains($html, 'name="preferred"')
        && !str_contains($html, 'name="preferred[]"')
        && preg_match('/value="7"[^>]* checked/', $html) === 1
        && preg_match('/value="9"[^>]* checked/', $html) === 0;
});

check('pills() senza etichetta nello schema: nessun titolo', function () {
    PageAssets::reset();
    $html = (new CheckGroup('preferred'))->options(['1' => 'Uno'])->pills()->label('')->render('wonder');

    return !str_contains($html, 'class="wi-label"');
});

check('il testo delle opzioni e il segno sono escapati', function () {
    PageAssets::reset();
    $html = (new CheckGroup('x'))
        ->options(['a' => ['name' => '<b>Rosso</b>', 'color' => '"><script>alert(1)</script>']])
        ->pills()
        ->render('wonder');

    return !str_contains($html, '<script>alert(1)')
        && !str_contains($html, '<b>Rosso</b>')
        && str_contains($html, '&lt;b&gt;Rosso&lt;/b&gt;');
});

check('senza pills() resta la lista della lib, con il segno davanti al nome', function () use ($colors) {
    PageAssets::reset();
    $html = $colors()->pills(false)->render('wonder');

    return str_contains($html, 'class="wi-checkbox-list"')
        && !str_contains($html, 'wi-check-pill"')
        && substr_count($html, 'class="wi-checkbox-container"') === 4
        && str_contains($html, '</i> Rosso</label>');
});

check('il CSS esce una volta per pagina, in lista e in pillole', function () use ($colors) {
    PageAssets::reset();
    $first = $colors()->render('wonder');
    $second = $colors()->pills(false)->render('wonder');

    return substr_count($first, '<style data-wi-check-group-style>') === 1
        && str_contains($first, '.wi-check-pill-label')
        && str_contains($first, '.wi-checkbox-container { position: relative; display: flow-root; }')
        && !str_contains($second, 'data-wi-check-group-style');
});

summary();
