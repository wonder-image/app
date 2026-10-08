<?php
/** php tests/App/DefaultFontsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Resources\Css\CssFontResource;
use Wonder\App\RuntimeDefaults;
use Wonder\App\Support\CssFontFamily;

$root = dirname(__DIR__, 2);
$prefix = '/vendor/wonder-image/app/';
$fonts = [];

foreach (RuntimeDefaults::defaultFonts() as $font) {
    $fonts[$font['name']] = $font;
}

$locali = ['Inter', 'Open Sans', 'Lato', 'Poppins', 'DM Sans', 'Nunito', 'Work Sans'];

check('i font predefiniti di css_font sono i 9 del sito', fn () =>
    array_keys($fonts) === ['Roboto', 'Montserrat', ...$locali]
);

check('Roboto e Montserrat restano quelli di Google', fn () =>
    str_starts_with($fonts['Roboto']['link'], 'https://fonts.googleapis.com/')
    && str_starts_with($fonts['Montserrat']['link'], 'https://fonts.googleapis.com/')
);

check('i 7 nuovi si servono dal pacchetto, con un solo foglio di stile', function () use ($fonts, $locali, $prefix) {
    foreach ($locali as $name) {
        if ($fonts[$name]['link'] !== $prefix.'resources/assets/font/web/fonts.css'
            || $fonts[$name]['font-family'] !== '"'.$name.'", sans-serif'
            || CssFontFamily::normalize($fonts[$name]['font-family']) !== $fonts[$name]['font-family']) {
            echo "    {$name}\n";

            return false;
        }
    }

    return true;
});

check('il foglio dichiara ogni font nuovo e ogni file che nomina esiste', function () use ($root, $locali) {
    $file = $root.'/resources/assets/font/web/fonts.css';
    $css = (string) @file_get_contents($file);

    foreach ($locali as $name) {
        if (!str_contains($css, '@font-face{font-family:"'.$name.'";')) {
            echo "    manca {$name}\n";

            return false;
        }
    }

    preg_match_all('/url\("([^"]+)"\)/', $css, $urls);

    foreach ($urls[1] as $url) {
        if (!is_file(dirname($file).'/'.$url)) {
            echo "    manca il file {$url}\n";

            return false;
        }
    }

    return count($urls[1]) >= count($locali);
});

check('il link di un font si scrive anche relativo al sito', fn () =>
    str_contains(CssFontResource::getInput('link')->render('bootstrap'), 'type="text"')
);

summary();
