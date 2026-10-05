<?php
/** php tests/Http/CsrfTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Auth\Frontend\AuthSession;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Form\Components\InputText;
use Wonder\Elements\Form\Form;
use Wonder\Http\Csrf;

// La sessione parte a metà file: senza buffer l'output dei primi check
// chiuderebbe gli header e `session_start()` darebbe un avviso.
ob_start();
ini_set('session.use_cookies', '0');
ini_set('session.save_path', sys_get_temp_dir());

$_SESSION = [];
$_POST = [];

$form = static fn (): Form => (new Form())->components([(new InputText('name'))->label('Nome')]);
$button = static fn (): Button => Button::post('/backend/sync/', 'Sincronizza');
$fields = static fn (string $html): int => substr_count($html, 'name="_csrf"');

check('il token nasce al primo uso e resta lo stesso', function () {
    $token = Csrf::token();

    return preg_match('/^[a-f0-9]{64}$/', $token) === 1 && Csrf::token() === $token;
});

check('AuthSession è una delega: stesso token, stessa chiave di sessione', function () {
    return AuthSession::csrfToken() === Csrf::token()
        && ($_SESSION['wonder_auth_csrf'] ?? null) === Csrf::token()
        && AuthSession::verify(Csrf::token())
        && !AuthSession::verify('wrong');
});

check('verify confronta una stringa', function () {
    return Csrf::verify(Csrf::token()) && !Csrf::verify('wrong') && !Csrf::verify('');
});

check('verify legge il campo _csrf da un array', function () {
    return Csrf::verify(['_csrf' => Csrf::token()])
        && !Csrf::verify(['_csrf' => 'wrong'])
        && !Csrf::verify(['_csrf' => [Csrf::token()]])
        && !Csrf::verify([]);
});

check('verify senza argomenti legge $_POST e poi l\'header X-WI-CSRF', function () {
    $empty = !Csrf::verify();

    $_POST = ['_csrf' => Csrf::token()];
    $post = Csrf::verify();

    $_POST = [];
    $_SERVER['HTTP_X_WI_CSRF'] = Csrf::token();
    $header = Csrf::verify();
    // L'header vale solo per la richiesta corrente, non per un array passato a mano.
    $array = !Csrf::verify([]);

    unset($_SERVER['HTTP_X_WI_CSRF']);

    return $empty && $post && $header && $array;
});

check('AuthSession::verify non ripiega sulla richiesta quando il token manca', function () {
    $_POST = ['_csrf' => Csrf::token()];
    $result = !AuthSession::verify(null) && !AuthSession::verify('');
    $_POST = [];

    return $result;
});

check('senza token in sessione nessun valore è valido', function () {
    $saved = $_SESSION;
    $_SESSION = [];
    $result = !Csrf::verify('') && !Csrf::verify(['_csrf' => '']) && !Csrf::verify();
    $_SESSION = $saved;

    return $result;
});

check('senza sessione attiva nessuna emissione automatica', function () use ($form, $button, $fields) {
    return !Csrf::active()
        && Csrf::fieldFor('post') === ''
        && $fields($form()->render('wonder')) === 0
        && $fields($form()->render('bootstrap')) === 0
        && $fields($button()->render('wonder')) === 0
        && $fields($button()->render('bootstrap')) === 0;
});

check('una risposta dichiarata pubblica non riceve il token', function () {
    session_cache_limiter('public');
    session_start();
    $result = !Csrf::active() && Csrf::fieldFor('post') === '';
    session_write_close();

    return $result;
});

session_cache_limiter('nocache');
session_start();

check('con la sessione il campo esce per i metodi che scrivono', function () use ($fields) {
    $html = Csrf::fieldFor('post');

    return Csrf::active()
        && $fields($html) === 1
        && str_contains($html, 'type="hidden"')
        && str_contains($html, 'value="'.Csrf::token().'"')
        && $fields(Csrf::fieldFor('PUT')) === 1
        && Csrf::fieldFor('get') === ''
        && Csrf::fieldFor(' GET ') === ''
        && Csrf::fieldFor('head') === '';
});

check('field() accetta un nome diverso', function () {
    return str_contains(Csrf::field('token')->render(), 'name="token"');
});

check('il renderer Form di ogni tema emette il campo una volta', function () use ($form, $fields) {
    return $fields($form()->render('wonder')) === 1 && $fields($form()->render('bootstrap')) === 1;
});

check('Button::post emette il campo dentro il proprio form, in ogni tema', function () use ($button, $fields) {
    foreach (['wonder', 'bootstrap'] as $theme) {
        $html = $button()->render($theme);

        if ($fields($html) !== 1 || preg_match('/<form[^>]*>\s*<input type="hidden" name="_csrf"/', $html) !== 1) {
            return false;
        }
    }

    return true;
});

check('un Button che non è POST resta senza campo', function () use ($fields) {
    return $fields(Button::make('Apri')->href('/backend/')->render('bootstrap')) === 0;
});

session_write_close();
ob_end_flush();

summary();
