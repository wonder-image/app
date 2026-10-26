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

check('il CSS delle pillole esce una volta per pagina; la lista usa solo la lib', function () use ($colors) {
    PageAssets::reset();
    $list = $colors()->pills(false)->render('wonder');
    $first = $colors()->render('wonder');
    $second = $colors()->render('wonder');

    return !str_contains($list, 'data-wi-check-group-style')
        && substr_count($first, '<style data-wi-check-group-style>') === 1
        && str_contains($first, '.wi-check-pill-label')
        && !str_contains($first, '.wi-checkbox-container')
        && !str_contains($second, 'data-wi-check-group-style');
});

$pages = static fn (): CheckGroup => (new CheckGroup('pages'))
    ->label('Pagine')
    ->options(['home' => 'Home', 'shop' => 'Shop']);
$alert = "<span class='alert-error'><i class='bi bi-exclamation-triangle'></i> Scegli &lt;b&gt;una&lt;/b&gt; pagina</span>";

check('senza errore: contenitore senza input-error e nessun messaggio', function () use ($pages) {
    PageAssets::reset();
    $list = $pages()->render('wonder');
    $pills = $pages()->pills()->render('wonder');

    return str_contains($list, '<div class="wi-input-container checkbox compiled">')
        && str_contains($pills, '<div class="wi-input-container checkbox compiled">')
        && !str_contains($list.$pills, 'input-error')
        && !str_contains($list.$pills, 'alert-error');
});

check('con errore (lista): input-error sul contenitore e messaggio escapato dopo la lista', function () use ($pages, $alert) {
    $html = $pages()->error('Scegli <b>una</b> pagina')->render('wonder');

    return str_contains($html, '<div class="wi-input-container checkbox compiled input-error">')
        && substr_count($html, 'alert-error') === 1
        && preg_match('#</div>\s*</div>\s*'.preg_quote($alert, '#').'\s*</div>\s*$#', $html) === 1
        && !str_contains($html, 'class="wi-checkbox input-error"');
});

check('con errore e required: restano entrambe le classi del contenitore', function () use ($pages) {
    $html = $pages()->required()->error('Obbligatorio')->render('wonder');
    $radio = $pages()->inputType('radio')->required()->error('Obbligatorio')->render('wonder');

    return str_contains($html, 'class="wi-input-container checkbox compiled wi-checkbox-required input-error"')
        && str_contains($radio, 'class="wi-input-container checkbox compiled wi-radio-required input-error"');
});

check('con errore (pills): input-error sul contenitore e messaggio dopo le pillole', function () use ($pages, $alert) {
    PageAssets::reset();
    $html = $pages()->pills()->error('Scegli <b>una</b> pagina')->render('wonder');

    return str_contains($html, '<div class="wi-input-container checkbox compiled input-error">')
        && preg_match('#class="wi-check-pills">.*</div>\s*'.preg_quote($alert, '#').'\s*</div>\s*$#s', $html) === 1;
});

check('errore vuoto o di soli spazi: come senza errore', fn () =>
    !str_contains($pages()->error('  ')->render('wonder'), 'input-error')
);

summary();
