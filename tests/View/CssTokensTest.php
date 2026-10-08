<?php
/**
 * CssTokens: il template dei token del sito, usato da cssRoot()/cssColor() e
 * dal catalogo dei componenti con i valori di default.
 *
 *   php tests/View/CssTokensTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\View\CssTokens;

echo "CssTokens\n";

$css = CssTokens::defaultRoot();

check('defaultRoot() è un blocco :root con le variabili principali', function () use ($css) {
    return str_starts_with($css, ":root {\n") && str_ends_with($css, '}')
        && str_contains($css, '--spacer: 4px;')
        && str_contains($css, '--font-family: "Roboto", sans-serif;')
        && str_contains($css, '--title-big-font-size: 40px;')
        && str_contains($css, '--button-border-radius: 5px;')
        && str_contains($css, '--input-border-color: #DEDEDE;')
        && str_contains($css, '--modal-border-width: 1px;')
        && str_contains($css, '--alert-top: calc(var(--spacer) * 5);');
});

check('ogni colore della palette ha colore, contrasto, rgb e le undici opacità', function () use ($css) {
    return str_contains($css, "/* primary */\n--primary-color: #000000;\n--primary-o-color: #ffffff;\n--primary-color-rgb: 0, 0, 0;\n--primary-o-color-rgb: 255, 255, 255;")
        && str_contains($css, '--primary-color-0: rgba(var(--primary-color-rgb), 0);')
        && str_contains($css, '--primary-color-100: rgba(var(--primary-color-rgb), 1);')
        && str_contains($css, '--danger-color: #dc3545;')
        && str_contains($css, '--dark-o-color: var(--light-color);')
        && str_contains($css, '--dark-o-color-rgb: 0, 0, 0;');
});

check('testo e sfondo hanno le proprie opacità', function () use ($css) {
    return str_contains($css, "--tx-color: #000000;\n--tx-color-rgb: 0, 0, 0;")
        && str_contains($css, '--tx-color-50: rgba(var(--tx-color-rgb), 0.5);')
        && str_contains($css, "--bg-color: #ffffff;\n--bg-color-rgb: 255, 255, 255;");
});

check('root() usa le righe e i font passati', function () {
    $css = CssTokens::root(
        (object) array_merge(\Wonder\App\SeedDefaults::cssDefaultRow(), ['spacer' => 8, 'tx_color' => '#abc']),
        (object) \Wonder\App\SeedDefaults::cssInputRow(),
        (object) \Wonder\App\SeedDefaults::cssAuthRow(),
        (object) \Wonder\App\SeedDefaults::cssModalRow(),
        (object) \Wonder\App\SeedDefaults::cssDropdownRow(),
        (object) \Wonder\App\SeedDefaults::cssAlertRow(),
        [['var' => 'brand', 'color' => '#123456', 'contrast' => '#ffffff']],
        ['default' => '"Inter", sans-serif', 'title' => '"Lora", serif'],
        '/img/default.jpg'
    );

    return str_contains($css, '--spacer: 8px;')
        && str_contains($css, "--default-image: url('/img/default.jpg');")
        && str_contains($css, '--font-family: "Inter", sans-serif;')
        && str_contains($css, '--title-font-family: "Lora", serif;')
        && str_contains($css, '--subtitle-font-family: "Inter", sans-serif;')
        && str_contains($css, '--brand-color-rgb: 18, 52, 86;')
        && str_contains($css, '--tx-color-rgb: 170, 187, 204;');
});

check('colorClasses() scrive le utility per ogni colore', function () {
    $css = CssTokens::colorClasses([['var' => 'brand', 'color' => '#123456', 'contrast' => '#ffffff']]);

    return str_starts_with($css, "/* Classi colori */\n")
        && str_contains($css, '.tx-brand { color: var(--brand-color) !important; }')
        && str_contains($css, '.bg-brand-o-50 { background: var(--brand-o-color-50) !important; }')
        && str_contains($css, '.badge.badge-brand, .btn.btn-brand { border-color: var(--brand-color-100); background: var(--brand-color-100); color: var(--brand-o-color); }')
        && str_contains(CssTokens::defaultColorClasses(), '.btn.btn-primary-o:hover');
});

check('un colore non esadecimale vale nero nei canali rgb', function () {
    $css = CssTokens::colorClasses([]);
    $root = CssTokens::root(
        (object) \Wonder\App\SeedDefaults::cssDefaultRow(),
        (object) \Wonder\App\SeedDefaults::cssInputRow(),
        (object) \Wonder\App\SeedDefaults::cssAuthRow(),
        (object) \Wonder\App\SeedDefaults::cssModalRow(),
        (object) \Wonder\App\SeedDefaults::cssDropdownRow(),
        (object) \Wonder\App\SeedDefaults::cssAlertRow(),
        [['var' => 'x', 'color' => 'rgb(1,2,3)', 'contrast' => '#fff']],
        []
    );

    return $css === "/* Classi colori */\n" && str_contains($root, '--x-color-rgb: 0, 0, 0;') && str_contains($root, '--x-o-color-rgb: 255, 255, 255;');
});

summary();
