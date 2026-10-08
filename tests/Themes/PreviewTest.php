<?php
/**
 * Preview: finestra con sorgenti selezionabili, schema chiaro/scuro e
 * iframe, uguale nei due temi.
 *
 *   php tests/Themes/PreviewTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Preview;
use Wonder\Themes\Support\PageAssets;

echo "Preview\n";

$box = static fn (): Preview => Preview::make('Bottone <b>')
    ->source('wonder', 'Wonder', '/p?theme=wonder')
    ->source('bootstrap', 'Bootstrap', '/p?theme=bootstrap', ['schemes' => true, 'icon' => 'bi bi-bootstrap'])
    ->source('altro', 'Altro', null, ['reason' => 'Nessun renderer'])
    ->active('bootstrap')
    ->group('docs')
    ->height(120);

foreach (['wonder', 'bootstrap'] as $theme) {
    check("{$theme}: radice con gruppo, sorgente attiva, schema e altezza minima", function () use ($box, $theme) {
        PageAssets::reset();
        $html = $box()->render($theme);

        return str_contains($html, '<div class="wi-preview" data-wi-preview data-wi-preview-group="docs" data-wi-preview-active="bootstrap" data-wi-preview-scheme="light" data-wi-preview-auto-height="true" style="--wi-preview-min-height: 120px">')
            && str_contains($html, '<div class="wi-preview-title">Bottone &lt;b&gt;</div>')
            && str_contains($html, '<iframe class="wi-preview-iframe"')
            && str_contains($html, 'title="Bottone &lt;b&gt;"');
    });

    check("{$theme}: una scheda per sorgente, con url, schemi, icona e stato", function () use ($box, $theme) {
        PageAssets::reset();
        $html = $box()->render($theme);

        return str_contains($html, 'class="wi-preview-tab" role="tab" data-wi-preview-source="wonder" data-wi-preview-schemes="false" data-wi-preview-reload="false" data-wi-preview-url="/p?theme=wonder" aria-selected="false">Wonder</button>')
            && str_contains($html, 'class="wi-preview-tab is-active" role="tab" data-wi-preview-source="bootstrap" data-wi-preview-schemes="true" data-wi-preview-reload="false" data-wi-preview-url="/p?theme=bootstrap" aria-selected="true"><i class="bi bi-bootstrap" aria-hidden="true"></i> Bootstrap</button>')
            && str_contains($html, 'data-wi-preview-source="altro" data-wi-preview-schemes="false" data-wi-preview-reload="false" aria-selected="false" disabled aria-disabled="true" title="Nessun renderer">Altro</button>');
    });

    check("{$theme}: CSS e script una volta sola", function () use ($box, $theme) {
        PageAssets::reset();
        $first = $box()->render($theme);
        $second = $box()->render($theme);

        return str_contains($first, '<style data-wi-preview-style>') && str_contains($first, '<script data-wi-preview-script>')
            && !str_contains($second, '<style data-wi-preview-style>') && !str_contains($second, '<script data-wi-preview-script>');
    });
}

check('senza active() la prima sorgente disponibile è attiva, le spente non contano', function () {
    PageAssets::reset();
    $html = Preview::make()->source('a', 'A', null)->source('b', 'B', '/b')->render('bootstrap');

    return str_contains($html, 'data-wi-preview-active="b"');
});

check('srcdoc rende la sorgente disponibile senza url', function () {
    PageAssets::reset();
    $html = Preview::make()->source('inline', 'Inline', null, ['srcdoc' => '<p>ciao</p>'])->render('bootstrap');

    return str_contains($html, 'data-wi-preview-srcdoc="&lt;p&gt;ciao&lt;/p&gt;"')
        && !str_contains($html, ' disabled aria-disabled');
});

check('il bottone chiaro/scuro e il link apri sono nella barra; openInNewTab(false) toglie il link', function () {
    PageAssets::reset();
    $with = Preview::make()->source('a', 'A', '/a')->render('wonder');
    $without = Preview::make()->source('a', 'A', '/a')->openInNewTab(false)->render('wonder');

    return str_contains($with, 'data-wi-preview-scheme-toggle') && str_contains($with, 'data-wi-preview-open')
        && !str_contains($without, 'data-wi-preview-open');
});

check('chiavi, gruppo e schema non validi vengono rifiutati', function () {
    try { Preview::make()->source('non valida', 'x', '/'); return false; } catch (InvalidArgumentException) {}
    try { Preview::make()->group('un gruppo'); return false; } catch (InvalidArgumentException) {}
    try { Preview::make()->scheme('blu'); return false; } catch (InvalidArgumentException) {}

    return true;
});

check('columnSpan() esplicito incarta il riquadro', function () {
    PageAssets::reset();
    $html = Preview::make()->source('a', 'A', '/a')->columnSpan(4)->render('bootstrap');

    return str_contains($html, '<div class="col-span-4"><div class="wi-preview"');
});

summary();
