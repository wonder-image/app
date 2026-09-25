<?php
/** php tests/Themes/ModalTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Form\Form;

/**
 * Una finestra scritta nel layout come un Accordion: titolo, corpo a
 * griglia, bottoni in fondo. Non ha un <form> dentro e lo script la sposta
 * in fondo al body, così i suoi campi non partono con la scheda.
 */
$finestra = static fn (): Modal => Modal::make('Costo · Rosso <L>')
    ->id('wi-cost-modal')
    ->columns(12)
    ->components([
        FormField::key('wi_cost_code')->text()->label('Codice fornitore')->columnSpan(6),
        FormField::key('wi_cost_price')->price()->label('Costo')->columnSpan(6),
    ])
    ->footer([
        Button::make('Annulla')->variant('secondary')->attr('data-bs-dismiss', 'modal'),
        Button::make('Salva')->attr('data-wi-cost-save', 'true'),
    ]);

check('la cornice è un .modal.fade da staccare, con dialogo centrato', function () use ($finestra) {
    $html = $finestra()->render('bootstrap');

    return str_contains($html, 'class="modal fade"')
        && str_contains($html, 'id="wi-cost-modal"')
        && str_contains($html, 'tabindex="-1"')
        && str_contains($html, 'data-wi-modal-detach')
        && str_contains($html, '<div class="modal-dialog modal-dialog-centered">')
        && str_contains($html, '<div class="modal-content">');
});

check('il titolo sta in h5.modal-title[data-wi-modal-title], escapato, con la X', function () use ($finestra) {
    $html = $finestra()->render('bootstrap');

    return str_contains($html, '<h5 class="modal-title" data-wi-modal-title>Costo · Rosso &lt;L&gt;</h5>')
        && str_contains($html, 'class="btn-close" data-bs-dismiss="modal"');
});

check('il corpo è una griglia e contiene i campi', function () use ($finestra) {
    $html = $finestra()->render('bootstrap');

    return str_contains($html, '<div class="modal-body row g-3">')
        && str_contains($html, 'name="wi_cost_code"')
        && str_contains($html, 'name="wi_cost_price"');
});

check('in fondo i bottoni, in fila e con i loro attributi', function () use ($finestra) {
    $html = $finestra()->render('bootstrap');
    $fondo = substr($html, (int) strpos($html, '<div class="modal-footer">'));
    $script = strpos($fondo, '<script');
    $fondo = $script === false ? $fondo : substr($fondo, 0, $script);

    return str_contains($html, '<div class="modal-footer">')
        && str_contains($fondo, 'data-bs-dismiss="modal"')
        && str_contains($fondo, '>Annulla</button>')
        && str_contains($fondo, 'data-wi-cost-save="true"')
        && str_contains($fondo, '>Salva</button>')
        && !str_contains($fondo, '<div class="col');
});

check('dentro non c\'è mai un <form>', function () use ($finestra) {
    return !str_contains($finestra()->render('bootstrap'), '<form');
});

check('size() e scrollable() vanno sul dialogo', function () {
    $html = Modal::make('Costo')->size('lg')->scrollable()->render('bootstrap');

    return str_contains($html, '<div class="modal-dialog modal-dialog-centered modal-lg modal-dialog-scrollable">');
});

check('una misura che non esiste è un errore', function () {
    try {
        Modal::make('Costo')->size('xxl');
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('senza id la finestra se ne inventa uno', function () {
    return preg_match('/class="modal fade" [^>]*id="wi-modal-[a-z0-9]+"|id="wi-modal-[a-z0-9]+"[^>]*class="modal fade"/i', Modal::make('Costo')->render('bootstrap')) === 1;
});

check('sul tema Wonder la finestra non si disegna', function () use ($finestra) {
    return $finestra()->render('wonder') === '';
});

check('nel layout del form la finestra si rende senza colonna, con i campi nella sua griglia', function () use ($finestra) {
    $html = ResourceFormLayoutRenderer::render(
        (new Form)->components([
            (new Card)->columns(12)->components([
                FormField::key('name')->text()->label('Nome')->columnSpan(12),
            ]),
            $finestra(),
        ])->columns(12)
    );
    $corpo = substr($html, (int) strpos($html, '<div class="modal-body row g-3">'));

    return str_contains($html, 'data-wi-modal-detach')
        && preg_match('/<div class="col[^"]*"[^>]*>\s*<div[^>]*class="modal fade"/', $html) === 0
        && str_contains($corpo, '<div class="col-6">')
        && str_contains($corpo, 'name="wi_cost_code"')
        && str_contains($corpo, 'name="wi_cost_price"')
        && str_contains($html, 'data-wi-cost-save="true"')
        && substr_count($html, '<form') === 1;
});

check('anche renderLayout() rende la finestra con la sua griglia', function () use ($finestra) {
    $html = ResourceFormLayoutRenderer::renderLayout((new Form)->components([$finestra()])->columns(12));
    $corpo = substr($html, (int) strpos($html, '<div class="modal-body row g-3">'));

    return str_contains($html, 'data-wi-modal-detach')
        && str_contains($corpo, '<div class="col-6">')
        && str_contains($corpo, 'name="wi_cost_code"');
});

check('lo script che sposta le finestre nel body esce una volta sola', function () {
    $code = 'require "vendor/autoload.php";'
        .'$a = Wonder\Elements\Components\Modal::make("Uno")->render("bootstrap");'
        .'$b = Wonder\Elements\Components\Modal::make("Due")->render("bootstrap");'
        .'$c = Wonder\Backend\Support\ResourceFormLayoutRenderer::render((new Wonder\Elements\Form\Form)->components([Wonder\Elements\Components\Modal::make("Tre")]));'
        .'$all = $a.$b.$c;'
        .'echo json_encode(['
        .'"script" => substr_count($all, "<script"),'
        .'"selector" => substr_count($all, ".modal[data-wi-modal-detach]"),'
        .'"first" => str_contains($a, "<script"),'
        .'"body" => str_contains($all, "document.body.appendChild"),'
        .']);';
    $cmd = escapeshellarg(PHP_BINARY).' -r '.escapeshellarg($code);
    $out = (string) shell_exec('cd '.escapeshellarg(dirname(__DIR__, 2)).' && '.$cmd.' 2>&1');
    $result = json_decode($out, true);

    if (!is_array($result)) {
        throw new RuntimeException('Uscita inattesa: '.$out);
    }

    return $result === ['script' => 1, 'selector' => 1, 'first' => true, 'body' => true];
});

summary();
