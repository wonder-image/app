<?php
/**
 * PageAssets: un frammento per chiave, una volta sola per processo.
 *
 *   php tests/Themes/PageAssetsTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Themes\Support\PageAssets;

echo "PageAssets\n";

check('la prima richiesta stampa, le altre no', function () {
    PageAssets::reset();

    return PageAssets::once('x', '<style></style>') === '<style></style>'
        && PageAssets::once('x', '<style></style>') === ''
        && PageAssets::emitted('x');
});

check('una callable viene eseguita solo la prima volta', function () {
    PageAssets::reset();
    $calls = 0;
    $html = static function () use (&$calls): string { $calls++; return 'a'; };

    PageAssets::once('y', $html);
    PageAssets::once('y', $html);

    return $calls === 1;
});

check('chiavi diverse non si pestano e reset() dimentica tutto', function () {
    PageAssets::reset();
    PageAssets::once('a', 'a');
    $other = PageAssets::once('b', 'b');
    PageAssets::reset();

    return $other === 'b' && !PageAssets::emitted('a') && PageAssets::once('a', 'a') === 'a';
});

check('una chiave vuota non stampa mai', function () {
    PageAssets::reset();

    return PageAssets::once('', 'x') === '' && PageAssets::once('  ', 'x') === '';
});

summary();
