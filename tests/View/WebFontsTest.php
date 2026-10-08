<?php
/** php tests/View/WebFontsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\View\WebFonts;

$base = 'https://sito.test/vendor/wonder-image/app/resources/assets/font/web';

check('il catalogo ha i 9 font, nell\'ordine del menu', fn () =>
    WebFonts::all() === [
        'inter' => 'Inter',
        'roboto' => 'Roboto',
        'open-sans' => 'Open Sans',
        'lato' => 'Lato',
        'montserrat' => 'Montserrat',
        'poppins' => 'Poppins',
        'dm-sans' => 'DM Sans',
        'nunito' => 'Nunito',
        'work-sans' => 'Work Sans',
    ]
);

check('ogni file esiste ed è un woff2', function () {
    foreach (array_keys(WebFonts::all()) as $key) {
        $files = WebFonts::files($key);

        if ($files === []) {
            return false;
        }

        foreach ($files as $file) {
            if (!is_file($file) || file_get_contents($file, false, null, 0, 4) !== 'wOF2') {
                echo "    manca o non è woff2: {$file}\n";

                return false;
            }
        }
    }

    return true;
});

check('ogni famiglia ha la licenza OFL accanto ai file', function () {
    foreach (array_keys(WebFonts::all()) as $key) {
        $license = dirname(WebFonts::files($key)[0]).'/LICENSE';

        if (!is_file($license) || !str_contains((string) file_get_contents($license), 'Open Font License')) {
            echo "    licenza mancante: {$license}\n";

            return false;
        }
    }

    return true;
});

check('css() di un font variable: un @font-face con intervallo di pesi e le variabili', function () use ($base) {
    $css = WebFonts::css('inter', $base);

    return substr_count($css, '@font-face') === 1
        && str_contains($css, 'font-family:"Inter"')
        && str_contains($css, 'font-weight:100 900')
        && str_contains($css, 'font-display:swap')
        && str_contains($css, 'url("'.$base.'/Inter/inter-latin-wght-normal.woff2") format("woff2")')
        && str_contains($css, 'html:root{')
        && str_contains($css, '--font-family:"Inter", sans-serif;')
        && str_contains($css, '--title-big-font-family:"Inter", sans-serif;')
        && str_contains($css, '--title-font-family:"Inter", sans-serif;')
        && str_contains($css, '--subtitle-font-family:"Inter", sans-serif;')
        && str_contains($css, '--text-font-family:"Inter", sans-serif;')
        && str_contains($css, '--text-small-font-family:"Inter", sans-serif;')
        && !str_contains($css, '<style');
});

check('css() dei font statici: un @font-face per peso', fn () =>
    substr_count(WebFonts::css('lato', $base), '@font-face') === 2
    && str_contains(WebFonts::css('lato', $base), 'font-weight:700;')
    && substr_count(WebFonts::css('poppins', $base), '@font-face') === 4
    && str_contains(WebFonts::css('poppins', $base), $base.'/Poppins/poppins-latin-600-normal.woff2')
);

check('chiave vuota o sconosciuta: nessun CSS; spazi e maiuscole non contano', fn () =>
    WebFonts::css('', $base) === ''
    && WebFonts::css('boh', $base) === ''
    && WebFonts::files('boh') === []
    && !WebFonts::has('')
    && WebFonts::has(' Open-Sans ')
    && str_contains(WebFonts::css(' Open-Sans ', $base), 'font-family:"Open Sans"')
);

check('l\'URL base perde virgolette e parentesi angolari', function () {
    $css = WebFonts::css('inter', 'https://x.test/a"b<c>/');

    return str_contains($css, 'url("https://x.test/abc/Inter/')
        && !str_contains($css, 'a"b');
});

check('senza URL base usa $PATH->appAssets; senza $PATH non stampa nulla', function () {
    unset($GLOBALS['PATH']);
    $none = WebFonts::css('inter');
    $GLOBALS['PATH'] = (object) ['appAssets' => 'https://sito.test/vendor/wonder-image/app/resources/assets'];
    $css = WebFonts::css('inter');
    unset($GLOBALS['PATH']);

    return $none === ''
        && str_contains($css, 'url("https://sito.test/vendor/wonder-image/app/resources/assets/font/web/Inter/inter-latin-wght-normal.woff2")');
});

check('variables() mette una famiglia di css_font nelle variabili del sito', function () {
    $css = WebFonts::variables('\\\'Montserrat\\\', sans-serif');

    return str_starts_with($css, 'html:root{')
        && str_contains($css, '--font-family:"Montserrat", sans-serif;')
        && str_contains($css, '--text-small-font-family:"Montserrat", sans-serif;')
        && substr_count($css, '"Montserrat", sans-serif') === 6;
});

check('variables() di una famiglia vuota non stampa nulla; una famiglia non esce dal blocco', fn () =>
    WebFonts::variables('  ') === ''
    && !preg_match('/[<>]|\}.*\{/', WebFonts::variables('"A"}</style><script>{x;'))
    && substr_count(WebFonts::variables('"A"}</style><script>{x;'), '}') === 1
);

summary();
