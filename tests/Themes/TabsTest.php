<?php
/** php tests/Themes/TabsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

if (!function_exists('__t')) {
    function __t(string $key): string
    {
        return $key;
    }
}

// Il token CSRF esce solo con una sessione attiva.
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
session_id('t');
@session_start();
$_SESSION = [];

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Tabs;
use Wonder\Elements\Form\Form;

$due = static fn (): Tabs => Tabs::make('f')
    ->tab('export', 'Esporta', ['<p>Contenuto export</p>'])
    ->tab('import', 'Importa', ['<p>Contenuto import</p>']);

check('due schede: barra e due pannelli, la prima attiva', function () use ($due) {
    $html = $due()->render('bootstrap');

    return str_contains($html, '<ul class="nav nav-tabs"')
        && substr_count($html, 'data-bs-toggle="tab"') === 2
        && (bool) preg_match('/id="f-export"[^>]*>/', $html)
        && (bool) preg_match('/class="tab-pane fade show active" id="f-export"/', $html)
        && (bool) preg_match('/class="tab-pane fade" id="f-import"/', $html)
        && (bool) preg_match('/class="nav-link active"[^>]*data-bs-target="#f-export"/', $html)
        && str_contains($html, 'aria-controls="f-import"')
        && str_contains($html, 'tab-content');
});

check('active() apre la scheda chiesta', function () use ($due) {
    $html = $due()->active('import')->render('bootstrap');

    return (bool) preg_match('/class="tab-pane fade show active" id="f-import"/', $html)
        && (bool) preg_match('/class="tab-pane fade" id="f-export"/', $html)
        && str_contains($html, 'aria-selected="true"');
});

check('una sola scheda: niente barra né tab-pane', function () {
    $html = Tabs::make('f')->tab('solo', 'Solo', ['<p>Contenuto solo</p>'])->render('bootstrap');

    return !str_contains($html, 'nav-tabs')
        && !str_contains($html, 'tab-pane')
        && str_contains($html, 'Contenuto solo');
});

check('l\'etichetta è escapata', function () {
    $html = Tabs::make('f')->tab('a', '<b>X</b>', [])->tab('b', 'Y', [])->render('bootstrap');

    return str_contains($html, '&lt;b&gt;X&lt;/b&gt;') && !str_contains($html, '<b>X</b>');
});

check('l\'id viene ripulito', function () {
    $html = Tabs::make('Mio Id!')->tab('a', 'A', [])->tab('b', 'B', [])->render('bootstrap');

    return str_contains($html, 'id="mio-id-a"');
});

check('tabs() restituisce le schede in ordine', function () use ($due) {
    $tabs = $due()->tabs();

    return count($tabs) === 2 && $tabs[0]['key'] === 'export' && $tabs[1]['label'] === 'Importa';
});

// Stessa classe di colonna che dà l'Accordion nel layout di una Resource.
check('un pannello con campi ha la griglia', function () {
    $campo = static fn () => FormField::key('x')->text()->columnSpan(6);
    $tabs = Tabs::make('f')->columns(12)
        ->tab('a', 'A', [$campo()])->tab('b', 'B', [$campo()]);

    $html = ResourceFormLayoutRenderer::render((new Form)->components([$tabs])->columns(12));
    $accordion = ResourceFormLayoutRenderer::render((new Form)->components([
        (new Accordion('T'))->columns(12)->components([$campo()]),
    ])->columns(12));

    return str_contains($accordion, 'class="col-6"')
        && substr_count($html, 'class="col-6"') === 2
        && str_contains($html, 'tab-pane');
});

check('un Form con action() dentro un pannello resta un form', function () {
    $form = (new Form)->action('/backend/x/', 'post')->components([FormField::key('f')->text()]);
    $html = ResourceFormLayoutRenderer::render((new Form)->components([
        Tabs::make('f')->tab('a', 'A', [$form])->tab('b', 'B', []),
    ])->columns(12));

    return str_contains($html, 'action="/backend/x/"') && substr_count($html, '<form') === 2;
});

summary();
