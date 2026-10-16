<?php
/** php tests/Http/SafeRedirectTest.php */
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/info.php';

use Wonder\Http\SafeRedirect;

$_ENV['APP_URL'] = 'https://example.com';

foreach ([
    'host esterno' => 'https://evil.com/',
    'host esterno http' => 'http://evil.com/backend/',
    'protocol-relative' => '//evil.com',
    'protocol-relative con barra rovescia' => '/\\evil.com',
    'barra rovescia doppia' => '\\\\evil.com',
    'javascript:' => 'javascript:alert(1)',
    'data:' => 'data:text/html,<script>alert(1)</script>',
    'suffisso del dominio' => 'https://example.com.evil.com/',
    'credenziali' => 'https://example.com@evil.com/',
    'utente sullo stesso host' => 'https://user@example.com/',
    'porta diversa' => 'https://example.com:444/',
    'downgrade a http' => 'http://example.com/backend/',
    'CR/LF (header injection)' => "/backend/\r\nLocation: https://evil.com",
    'tab' => "/\t/evil.com",
    'byte nullo' => "/backend/\0",
    'path relativo senza barra' => 'evil.com',
] as $name => $url) {
    check("rifiuta {$name}", fn () => SafeRedirect::fromRequest($url, '/fallback/') === '/fallback/');
}

check('rifiuta vuoto e non scalari', fn () => SafeRedirect::fromRequest('', '/f/') === '/f/'
    && SafeRedirect::fromRequest(['/x/'], '/f/') === '/f/'
    && SafeRedirect::fromRequest(null, '/f/') === '/f/');

check('accetta un path interno invariato', fn () => SafeRedirect::fromRequest('/backend/news/list.php?news__page=2', '/f/') === '/backend/news/list.php?news__page=2');
check('accetta lo stesso host di APP_URL', fn () => SafeRedirect::fromRequest('https://example.com/backend/', '/f/') === 'https://example.com/backend/');
check('accetta la variante www (ListProvider::redirect)', fn () => SafeRedirect::fromRequest('https://www.example.com/backend/x/list.php?x__page=2', '/f/') === 'https://www.example.com/backend/x/list.php?x__page=2');
check('host case-insensitive', fn () => SafeRedirect::fromRequest('https://WWW.Example.COM/a/', '/f/') === 'https://WWW.Example.COM/a/');

$_ENV['APP_URL'] = 'http://example.test';
check('APP_URL http: accetta la promozione a https', fn () => SafeRedirect::fromRequest('https://www.example.test/a/', '/f/') === 'https://www.example.test/a/');

$_ENV['APP_URL'] = '';
check('senza APP_URL passano solo i path', fn () => SafeRedirect::fromRequest('https://example.com/', '/f/') === '/f/'
    && SafeRedirect::fromRequest('/a/', '/f/') === '/a/');

$_ENV['APP_URL'] = 'https://example.com';

check('fromBase64: path interno', fn () => SafeRedirect::fromBase64(base64_encode('/backend/'), '') === '/backend/');
check('fromBase64: host esterno -> fallback', fn () => SafeRedirect::fromBase64(base64_encode('https://evil.com/'), '') === '');
check('fromBase64: base64 non valido -> fallback', fn () => SafeRedirect::fromBase64('%%%not-base64', '') === '');

check('alias Auth\\Frontend\\SafeRedirect', fn () => \Wonder\Auth\Frontend\SafeRedirect::fromRequest('//evil.com', '/f/') === '/f/');

unset($_SERVER['HTTP_HOST']);

check('infoPage: ?redirect= esterno viene scartato', function () {
    $_GET['redirect'] = base64_encode('https://evil.com/');
    $PAGE = infoPage();
    return $PAGE->redirect === '' && $PAGE->redirectBase64 === '';
});

check('infoPage: ?redirect= //host viene scartato', function () {
    $_GET['redirect'] = base64_encode('//evil.com/');
    $PAGE = infoPage();
    return $PAGE->redirect === '' && $PAGE->redirectBase64 === '';
});

check('infoPage: ?redirect= interno resta quello di oggi', function () {
    $url = 'https://www.example.com/backend/news/list.php?news__page=1&news__length=10';
    $_GET['redirect'] = base64_encode($url);
    $PAGE = infoPage();
    return $PAGE->redirect === $url && $PAGE->redirectBase64 === $_GET['redirect'];
});

check('infoPage: senza ?redirect= nessuna proprietà', function () {
    unset($_GET['redirect']);
    $PAGE = infoPage();
    return !isset($PAGE->redirect) && !isset($PAGE->redirectBase64);
});

summary();
