<?php
/** php tests/Themes/AlertInlineTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Theme;
use Wonder\Elements\Components\Alert;

// Toast catturato prima di inline(), con id fisso: senza inline() non deve cambiare di un byte.
$cases = [
    'info' => fn () => Alert::make('Ciao', 'info')->id('a1'),
    'success' => fn () => Alert::make('Fatto', 'success')->id('a1'),
    'warning' => fn () => Alert::make('Occhio', 'warning')->id('a1'),
    'error' => fn () => Alert::make('Rotto', 'error')->id('a1'),
    'titolo, no chiusura, escape' => fn () => Alert::make("A<b> & \"c\"\nriga 2", 'warning')->id('a1')->title('T<i>')->dismissible(false),
    'attributi' => fn () => Alert::make('X', 'info')->id('a1')->attr('data-x', 'y"z'),
];

$golden = array (
  'bootstrap' => 
  array (
    'info' => '<div id=\'a1\' class=\'toast border-info overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-info border-bottom border-info\'> <i class=\'bi bi-info-circle me-2\'></i> <strong class=\'me-auto\'>Info</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Ciao</div></div>',
    'success' => '<div id=\'a1\' class=\'toast border-success overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-success border-bottom border-success\'> <i class=\'bi bi-check2-circle me-2\'></i> <strong class=\'me-auto\'>Success</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Fatto</div></div>',
    'warning' => '<div id=\'a1\' class=\'toast border-warning overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-warning border-bottom border-warning\'> <i class=\'bi bi-exclamation-triangle me-2\'></i> <strong class=\'me-auto\'>Warning</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Occhio</div></div>',
    'error' => '<div id=\'a1\' class=\'toast border-danger overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-danger border-bottom border-danger\'> <i class=\'bi bi-exclamation-triangle me-2\'></i> <strong class=\'me-auto\'>Danger</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>Rotto</div></div>',
    'titolo, no chiusura, escape' => '<div id=\'a1\' class=\'toast border-warning overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' ><div class=\'toast-header text-bg-warning border-bottom border-warning\'> <i class=\'bi bi-exclamation-triangle me-2\'></i> <strong class=\'me-auto\'>T&lt;i&gt;</strong></div><div class=\'toast-body bg-light\'>A&lt;b&gt; &amp; &quot;c&quot;<br />
riga 2</div></div>',
    'attributi' => '<div id=\'a1\' class=\'toast border-info overflow-hidden\' role=\'alert\' aria-live=\'assertive\' aria-atomic=\'true\' data-x="y&quot;z"><div class=\'toast-header text-bg-info border-bottom border-info\'> <i class=\'bi bi-info-circle me-2\'></i> <strong class=\'me-auto\'>Info</strong><button type=\'button\' class=\'btn-close\' data-bs-dismiss=\'toast\' aria-label=\'Close\'></button></div><div class=\'toast-body bg-light\'>X</div></div>',
  ),
  'wonder' => 
  array (
    'info' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-info-circle tx-primary\'></i> <b>Info</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Ciao</div></div>',
    'success' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-check2-circle tx-success\'></i> <b>Success</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Fatto</div></div>',
    'warning' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-exclamation-triangle tx-warning\'></i> <b>Warning</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Occhio</div></div>',
    'error' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-exclamation-triangle tx-danger\'></i> <b>Danger</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>Rotto</div></div>',
    'titolo, no chiusura, escape' => '<div id=\'a1\' class=\'wi-alert wi-show\' ><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-exclamation-triangle tx-warning\'></i> <b>T&lt;i&gt;</b></div><div class=\'wi-alert-body\'>A&lt;b&gt; &amp; &quot;c&quot;<br />
riga 2</div></div>',
    'attributi' => '<div id=\'a1\' class=\'wi-alert wi-show\' data-x="y&quot;z"><div class=\'wi-alert-header\'><i class=\'wi-alert-icon bi bi-info-circle tx-primary\'></i> <b>Info</b><i class=\'wi-alert-close bi bi-x-lg\' onclick="this.parentElement.parentElement.classList.remove(\'wi-show\')"></i></div><div class=\'wi-alert-body\'>X</div></div>',
  ),
);

echo "\ntoast invariato\n";

foreach (['bootstrap', 'wonder'] as $theme) {
    foreach ($cases as $name => $make) {
        check("{$theme}: senza inline() l'HTML resta quello di prima: {$name}", fn () =>
            $make()->render($theme) === $golden[$theme][$name]);

        check("{$theme}: inline(false) equivale a nessun inline(): {$name}", fn () =>
            $make()->inline()->inline(false)->render($theme) === $golden[$theme][$name]);
    }
}

echo "\ninline nel tema bootstrap\n";

$types = ['info' => 'info', 'success' => 'success', 'warning' => 'warning', 'error' => 'danger'];

foreach ($types as $level => $type) {
    check("livello {$level} → alert-{$type}, visibile, senza titolo né chiusura", fn () =>
        Alert::make('Testo', $level)->inline()->render('bootstrap')
            === '<div class="alert alert-'.$type.'" role="alert">Testo</div>');
}

check('classe extra nello stesso attributo class: riproduce le viste backend', fn () =>
    Alert::make('Sola lettura', 'warning')->inline()->addClass('mb-0')->render('bootstrap')
        === '<div class="alert alert-warning mb-0" role="alert">Sola lettura</div>');

check('titolo solo se impostato, escapato', fn () =>
    Alert::make('Corpo', 'success')->inline()->title('Fatto <ok>')->render('bootstrap')
        === '<div class="alert alert-success" role="alert"><strong class="alert-heading d-block">Fatto &lt;ok&gt;</strong>Corpo</div>');

check('dismissible esplicito: alert-dismissible e btn-close per alert', fn () =>
    Alert::make('Chiudimi', 'info')->inline()->dismissible()->render('bootstrap')
        === '<div class="alert alert-info alert-dismissible fade show" role="alert">Chiudimi'
            .'<button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Close"></button></div>');

check('dismissible(false) esplicito resta non chiudibile', fn () =>
    !str_contains(Alert::make('A')->inline()->dismissible(false)->render('bootstrap'), 'btn-close'));

check('messaggio escapato con gli a capo, come il toast', fn () =>
    Alert::make("A<b> & \"c\"\nriga 2", 'warning')->inline()->render('bootstrap')
        === "<div class=\"alert alert-warning\" role=\"alert\">A&lt;b&gt; &amp; &quot;c&quot;<br />\nriga 2</div>");

check('id solo se dato, attributi data-* escapati sul tag', fn () =>
    Alert::make('X', 'error')->inline()->id('avviso')->attr('data-x', 'y"z')->addClass('mb-0 small')->render('bootstrap')
        === '<div class="alert alert-danger mb-0 small" id="avviso" role="alert" data-x="y&quot;z">X</div>');

check('senza id() non viene generato un id', fn () =>
    !str_contains(Alert::make('X')->inline()->render('bootstrap'), 'id='));

check('in un Card bootstrap il figlio Alert in linea si rende', fn () =>
    str_contains(
        (new \Wonder\Elements\Components\Card())->components([Alert::make('Dentro', 'warning')->inline()])->render('bootstrap'),
        '<div class="alert alert-warning" role="alert">Dentro</div>'
    ));

echo "\ninline nel tema wonder\n";

foreach ($cases as $name => $make) {
    check("wonder: inline() è ignorato: {$name}", fn () =>
        $make()->inline()->render('wonder') === $golden['wonder'][$name]);
}

// In CLI il tema attivo è wonder: per (string) imposta bootstrap e poi lo ripristina.
check('(string) con tema attivo bootstrap rispetta inline()', function () {
    $previous = Theme::get();
    Theme::set('bootstrap');

    try {
        return (string) Alert::make('X', 'warning')->inline() === '<div class="alert alert-warning" role="alert">X</div>';
    } finally {
        Theme::set($previous);
    }
});

summary();
