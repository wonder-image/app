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

echo "\nCssTokens::designMd\n";

$designFonts = [
    'default' => '"Roboto", sans-serif',
    'title_big' => '"Playfair Display", serif',
    'title' => '"Lora", serif',
    'subtitle' => '"Inter", sans-serif',
    'text' => '"Source Sans 3", sans-serif',
    'text_small' => '"Fira Sans", sans-serif',
];

$md = CssTokens::designMd(
    (object) \Wonder\App\SeedDefaults::cssDefaultRow(),
    \Wonder\App\RuntimeDefaults::defaultColors(),
    $designFonts
);

check('designMd() apre con il commento di generazione e il titolo', function () use ($md) {
    return str_starts_with($md, '<!-- Generato da wonder-image/app (CssTokens::designMd)')
        && str_contains($md, "\n# Design tokens\n")
        && str_contains($md, 'Proiezione leggibile di `assets/{ASSETS_VERSION}/css/set-up/root.css` e `color.css`.');
});

check('designMd() ha una riga di palette per ogni colore, con il contrasto anche quando non è esadecimale', function () use ($md) {
    return str_contains($md, "| Ruolo | Colore | Contrasto (`-o`) | Variabili |\n|---|---|---|---|\n| primary | #000000 | #ffffff | `--primary-color`, `--primary-o-color`, `--primary-color-{0..100 passo 10}`, `--primary-o-color-{0..100 passo 10}` |\n")
        && str_contains($md, '| dark | #343a40 | var(--light-color) | `--dark-color`, `--dark-o-color`, `--dark-color-{0..100 passo 10}`, `--dark-o-color-{0..100 passo 10}` |')
        && str_contains($md, '| white | #ffffff | #000000 |')
        && substr_count($md, '-o-color-{0..100 passo 10}` |') === 10
        && str_contains($md, 'Testo di default: `--tx-color` #000000 (`--tx-color-{0..100 passo 10}`) · Sfondo di default: `--bg-color` #ffffff (`--bg-color-{0..100 passo 10}`)');
});

check('designMd() scrive le sei righe tipografiche con la famiglia di ciascun gruppo', function () use ($md) {
    return str_contains($md, '| default | "Roboto", sans-serif | 400 | 16px | 16px | `--font-family`, `--font-weight`, `--font-size`, `--line-height` |')
        && str_contains($md, '| title-big | "Playfair Display", serif | 500 | 40px | 40px | `--title-big-font-family`, `--title-big-font-weight`, `--title-big-font-size`, `--title-big-line-height` |')
        && str_contains($md, '| title | "Lora", serif | 500 | 32px | 32px | `--title-font-family`, `--title-font-weight`, `--title-font-size`, `--title-line-height` |')
        && str_contains($md, '| subtitle | "Inter", sans-serif | 400 | 24px | 24px | `--subtitle-font-family`, `--subtitle-font-weight`, `--subtitle-font-size`, `--subtitle-line-height` |')
        && str_contains($md, '| text | "Source Sans 3", sans-serif | 300 | 16px | 20px | `--text-font-family`, `--text-font-weight`, `--text-font-size`, `--text-line-height` |')
        && str_contains($md, '| text-small | "Fira Sans", sans-serif | 300 | 12px | 12px | `--text-small-font-family`, `--text-small-font-weight`, `--text-small-font-size`, `--text-small-line-height` |');
});

check('un gruppo senza famiglia eredita quella di default, come in root()', function () {
    $md = CssTokens::designMd(
        (object) \Wonder\App\SeedDefaults::cssDefaultRow(),
        [],
        ['default' => '"Inter", sans-serif', 'title' => '"Lora", serif']
    );

    return str_contains($md, '| title | "Lora", serif | 500 |')
        && str_contains($md, '| subtitle | "Inter", sans-serif | 400 |')
        && str_contains($md, '| text-small | "Inter", sans-serif | 300 |');
});

check('designMd() riporta spaziatura, bottoni e badge dal seed', function () use ($md) {
    return str_contains($md, "## Spaziatura e layout\n\n- `--spacer`: 4px\n- `--header-height`: 80px\n")
        && str_contains($md, "| Componente | Font size | Line height | Peso | Radius | Bordo |\n|---|---|---|---|---|---|\n| button | 14px | 16px | 400 | 5px | 2px |\n| badge | 12px | 12px | 400 | 5px | 1px |\n");
});

check('le classi utility elencano i ruoli della palette e le varianti hover', function () use ($md) {
    return str_contains($md, 'Per ogni ruolo `{r}` della palette (primary, secondary, success, info, danger, dark, light, gray, black, white):')
        && str_contains($md, '`.tx-{r}` `.tx-{r}-o` `.bg-{r}` `.bg-{r}-o` `.bg-{r}-{0..100 passo 10}` `.bg-{r}-o-{N}` `.tx-stroke-{r}`')
        && str_contains($md, '`.btn-{r}` `.btn-{r}-o` `.badge-{r}` `.badge-{r}-o` e le varianti `hover\:` di `tx-`/`bg-`.')
        && str_contains($md, "## Come usarlo\n\n- Un colore o un font proposto da una skill esterna si mappa su un ruolo o un gruppo qui sopra, oppure si scarta.\n- Voce, tono e personalità del brand non stanno qui: vedi `docs/ai/PRODUCT.md`.\n");
});

check('nessun segnaposto rimasto nel file generato', function () use ($md) {
    return !str_contains($md, '…') && !str_contains($md, '{$');
});

check('una palette vuota produce un file valido con la tabella senza righe', function () {
    $md = CssTokens::designMd((object) \Wonder\App\SeedDefaults::cssDefaultRow(), [], []);

    return str_contains($md, "| Ruolo | Colore | Contrasto (`-o`) | Variabili |\n|---|---|---|---|\n\nTesto di default:")
        && str_contains($md, 'Per ogni ruolo `{r}` della palette (nessun ruolo in css_color):')
        && str_contains($md, '| default |  | 400 | 16px | 16px |');
});

check('una riga colore senza contrasto ha la cella vuota e una senza var si salta, senza warning', function () {
    set_error_handler(static function (int $no, string $message): bool {
        throw new \ErrorException($message, 0, $no);
    });

    try {
        $md = CssTokens::designMd(
            (object) \Wonder\App\SeedDefaults::cssDefaultRow(),
            [
                ['var' => 'brand', 'color' => '#123456'],
                ['color' => '#000000', 'contrast' => '#ffffff'],
                (object) ['var' => 'accent', 'color' => '#abcdef', 'contrast' => '#000000'],
            ],
            []
        );
    } finally {
        restore_error_handler();
    }

    return str_contains($md, '| brand | #123456 |  | `--brand-color`, `--brand-o-color`, `--brand-color-{0..100 passo 10}`, `--brand-o-color-{0..100 passo 10}` |')
        && str_contains($md, '| accent | #abcdef | #000000 |')
        && substr_count($md, '-o-color-{0..100 passo 10}` |') === 2
        && str_contains($md, 'della palette (brand, accent):');
});

summary();
