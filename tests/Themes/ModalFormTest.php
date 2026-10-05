<?php
/** php tests/Themes/ModalFormTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

if (!function_exists('__t')) {
    function __t(string $key): string
    {
        return $key;
    }
}

use Wonder\App\ResourceSchema\FormField;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;
use Wonder\Elements\Form\Form;

// Il token CSRF esce solo con una sessione attiva.
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());
session_start();
$_SESSION = [];

$pagamento = static fn (): Modal => Modal::make('Pagamento <12>')
    ->id('pay-12')
    ->form('/backend/pay/', 'post', ['order_id' => 12, 'mode' => 'full'])
    ->components([FormField::key('amount')->text()->label('Importo')]);

$between = static function (string $html, string $from, string $to): string {
    $start = strpos($html, $from);
    if ($start === false) {
        return '';
    }
    $end = strpos($html, $to, $start);

    return substr($html, $start, $end === false ? null : $end - $start + strlen($to));
};

echo "Bootstrap\n";

check('il form avvolge corpo e bottoni, non la testata', function () use ($pagamento) {
    $html = $pagamento()->render('bootstrap');
    $form = strpos($html, '<form method="post" action="/backend/pay/">');

    return $form !== false
        && $form > strpos($html, 'class="btn-close"')
        && $form < strpos($html, '<div class="modal-body')
        && strpos($html, '</form>') > strpos($html, '<div class="modal-footer">')
        && substr_count($html, '<form') === 1;
});

check('un solo token CSRF e i campi nascosti', function () use ($pagamento) {
    $html = $pagamento()->render('bootstrap');

    return substr_count($html, 'name="_csrf"') === 1
        && str_contains($html, 'name="order_id"') && str_contains($html, 'value="12"')
        && str_contains($html, 'name="mode"') && str_contains($html, 'value="full"');
});

check('in fondo Annulla, che chiude, e poi Salva, che invia', function () use ($pagamento) {
    $footer = substr(($html = $pagamento()->render('bootstrap')), (int) strpos($html, '<div class="modal-footer">'));
    $cancel = strpos($footer, 'components.buttons.cancel');
    $save = strpos($footer, 'components.buttons.save');

    return $cancel !== false && $save !== false && $cancel < $save
        && preg_match('/<button[^>]*data-bs-dismiss="modal"[^>]*type="button"[^>]*>components\.buttons\.cancel/', $footer) === 1
        && preg_match('/<button[^>]*btn-primary[^>]*type="submit"[^>]*>components\.buttons\.save/', $footer) === 1;
});

check('cancel() e submit() cambiano testo e colore', function () use ($pagamento) {
    $html = $pagamento()->cancel('Indietro')->submit('Registra', 'success')->render('bootstrap');

    return str_contains($html, '>Indietro</button>')
        && preg_match('/btn-success[^>]*type="submit"[^>]*>Registra</', $html) === 1;
});

check('footer() scritto a mano prende il posto dei bottoni automatici', function () use ($pagamento) {
    $html = $pagamento()->footer([Button::make('Solo questo')->type('submit')])->render('bootstrap');

    return str_contains($html, 'Solo questo') && !str_contains($html, 'components.buttons.cancel');
});

check('submit() senza form() aggiunge solo Salva', function () {
    $html = Modal::make('X')->id('x')->submit('OK')->render('bootstrap');

    return str_contains($html, '>OK</button>') && !str_contains($html, '<form') && !str_contains($html, 'components.buttons.cancel');
});

check('con GET niente token', function () {
    $html = Modal::make('Cerca')->id('s')->form('/cerca/', 'get')->render('bootstrap');

    return str_contains($html, '<form method="get" action="/cerca/">') && !str_contains($html, '_csrf');
});

check('scrollable: il form è una colonna flex, così il corpo scorre', function () use ($pagamento) {
    $html = $pagamento()->scrollable()->render('bootstrap');

    return str_contains($html, '<form method="post" action="/backend/pay/" class="d-flex flex-column overflow-hidden">');
});

check('help() mette il tooltip accanto al titolo, fuori dall\'h5', function () use ($pagamento) {
    $html = $pagamento()->help('Resta <b>modificabile</b> & "aperto"')->render('bootstrap');
    $header = (string) substr($html, (int) strpos($html, '<div class="modal-header">'), (int) strpos($html, 'btn-close') - (int) strpos($html, '<div class="modal-header">'));

    return str_contains($header, '</h5><span')
        && str_contains($header, 'data-bs-toggle="tooltip"')
        && str_contains($header, 'Resta &lt;b&gt;modificabile&lt;/b&gt; &amp; &quot;aperto&quot;')
        && !str_contains($header, '<b>');
});

check('le classi delle parti si aggiungono a quelle del tema, un class per tag', function () use ($pagamento) {
    $html = $pagamento()->size('lg')
        ->dialogClass('my-dialog')->headerClass('bg-light')->titleClass('fs-6')
        ->bodyClass('p-0')->footerClass('justify-content-between')
        ->render('bootstrap');

    return str_contains($html, '<div class="modal-dialog modal-dialog-centered modal-lg my-dialog">')
        && str_contains($html, '<div class="modal-header bg-light">')
        && str_contains($html, '<h5 class="modal-title fs-6" data-wi-modal-title>')
        && str_contains($html, '<div class="modal-body row g-3 p-0">')
        && str_contains($html, '<div class="modal-footer justify-content-between">');
});

check('una Modal con form() nel layout della Resource si ferma', function () use ($pagamento) {
    try {
        ResourceFormLayoutRenderer::renderLayout((new Form)->components([$pagamento()]));
    } catch (\LogicException $e) {
        return true;
    }

    return false;
});

check('metodo e campi nascosti non validi si fermano subito', function () {
    $fails = 0;
    foreach ([
        static fn () => Modal::make('X')->form('/x', 'delete'),
        static fn () => Modal::make('X')->form('/x', 'post', ['a' => ['b']]),
        static fn () => Modal::make('X')->form('/x', 'post', ['' => 1]),
    ] as $build) {
        try {
            $build();
        } catch (\InvalidArgumentException $e) {
            $fails++;
        }
    }

    return $fails === 3;
});

echo "\nWonder\n";

check('wi-modal-form avvolge corpo e bottoni, con token e nascosti', function () use ($pagamento, $between) {
    $html = $pagamento()->frontend()->render('wonder');
    $form = $between($html, '<form', '</form>');

    return str_contains($html, '<form class="wi-modal-form" method="post" action="/backend/pay/">')
        && str_contains($form, '<div class="wi-modal-body no-scrollbar">')
        && str_contains($form, '<div class="wi-modal-footer d-flex j-content-end gap-3">')
        && substr_count($form, 'name="_csrf"') === 1
        && str_contains($form, 'name="order_id"')
        && strpos($html, '<form') > strpos($html, 'wi-modal-close');
});

check('Annulla chiude la wi-modal, poi Salva', function () use ($pagamento) {
    $html = $pagamento()->frontend()->render('wonder');
    $footer = substr($html, (int) strpos($html, 'wi-modal-footer'));

    return preg_match('/<button[^>]*class="btn btn-dark-o wi-close-modal[^"]*"[^>]*>components\.buttons\.cancel/', $footer) === 1
        && strpos($footer, 'components.buttons.cancel') < strpos($footer, 'components.buttons.save')
        && preg_match('/type="submit"[^>]*>components\.buttons\.save/', $footer) === 1;
});

check('help() nel titolo: data-wi-title escapato due volte', function () use ($pagamento) {
    $html = $pagamento()->frontend()->help('a <b> & "c"')->render('wonder');

    return str_contains($html, 'data-wi-toggle="tooltip"')
        && str_contains($html, 'data-wi-title="a &amp;lt;b&amp;gt; &amp;amp; &amp;quot;c&amp;quot;"')
        && str_contains($html, 'aria-label="a &lt;b&gt; &amp; &quot;c&quot;"')
        && strpos($html, 'data-wi-toggle') < strpos($html, '</h2>');
});

check('parti Wonder: dialog, header, title, body, footer', function () use ($pagamento) {
    $html = $pagamento()->frontend()
        ->dialogClass('w-wide')->headerClass('h-x')->titleClass('t-x')->bodyClass('b-x')->footerClass('f-x')
        ->render('wonder');

    return str_contains($html, '<div class="content wi-modal-content c-w w-wide" style="max-width:760px;">')
        && str_contains($html, '<div class="wi-modal-header h-x">')
        && str_contains($html, 'id="pay-12-title" class="wi-modal-title t-x"')
        && str_contains($html, '<div class="wi-modal-body no-scrollbar b-x">')
        && str_contains($html, '<div class="wi-modal-footer d-flex j-content-end gap-3 f-x">');
});

check('senza frontend() Wonder resta vuoto anche con form()', function () use ($pagamento) {
    return $pagamento()->render('wonder') === '';
});

summary();
