<?php
/**
 * Formato da ?format= e ultima scelta di export in sessione.
 *
 *   php tests/Backend/Support/ResourceDownloadChoiceTest.php
 */
declare(strict_types=1);

ob_start(); // l'output dei check non deve bloccare session_start()

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route as SfRoute;
use Symfony\Component\Routing\RouteCollection;
use Wonder\Backend\Support\ResourceDownloadController as C;

check('formatFrom: il percorso vince sulla query', fn () => C::formatFrom(['format' => 'csv'], ['format' => 'xlsx']) === 'csv');
check('formatFrom: senza percorso legge ?format=', fn () => C::formatFrom([], ['format' => 'XLSX']) === 'xlsx');
check('formatFrom: valori strani danno vuoto', fn () => C::formatFrom([], ['format' => 'x/../y']) === ''
    && C::formatFrom([], ['format' => ['a']]) === ''
    && C::formatFrom([], []) === '');

check('senza sessione niente scelta', function () {
    if (session_status() === PHP_SESSION_ACTIVE) {
        return false;
    }
    $warnings = 0;
    set_error_handler(function () use (&$warnings) { $warnings++; return true; });
    C::remember('products', 'csv', 3);
    $last = C::lastChoice('products');
    restore_error_handler();
    return $warnings === 0 && $last === null;
});

check('con sessione la scelta si rilegge', function () {
    @session_start();
    C::remember('products', 'csv', 3);
    $a = C::lastChoice('products') === ['format' => 'csv', 'layout' => 3];
    C::remember('products', 'xlsx', null);
    return $a && C::lastChoice('products') === ['format' => 'xlsx', 'layout' => null];
});

check('una slug diversa non vede la scelta', fn () => C::lastChoice('orders') === null);

// /export/ e /export/{format}/ convivono senza rubarsi l'url
check('rotte: /export/ non oscura /export/{format}/', function () {
    $routes = new RouteCollection();
    $routes->add('file', new SfRoute('/export/', ['n' => 'file']));
    $routes->add('export', new SfRoute('/export/{format}/', ['n' => 'export'], ['format' => '[a-z]+']));
    $m = new UrlMatcher($routes, new RequestContext());
    try {
        $m->match('/export/x1/');
        return false;
    } catch (ResourceNotFoundException) {
    }
    return $m->match('/export/')['n'] === 'file'
        && $m->match('/export/csv/')['n'] === 'export'
        && $m->match('/export/csv/')['format'] === 'csv';
});

ob_end_flush();
summary();
