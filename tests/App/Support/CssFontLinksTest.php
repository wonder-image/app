<?php
/** php tests/App/Support/CssFontLinksTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\CssFontLinks;

$google = 'https://fonts.googleapis.com/css2?family=Roboto';
$locale = '/vendor/wonder-image/app/resources/assets/font/web/fonts.css';

check('un link relativo al sito diventa assoluto, anche col sito in una cartella', fn () =>
    CssFontLinks::hrefs([['link' => $locale]], 'https://sito.test/') === ['https://sito.test'.$locale]
    && CssFontLinks::hrefs([['link' => $locale]], 'https://x.test/negozio') === ['https://x.test/negozio'.$locale]
);

check('Google Fonts prende display=swap, se non c\'è già', fn () =>
    CssFontLinks::hrefs([['link' => $google]], 'https://sito.test') === [$google.'&display=swap']
    && CssFontLinks::hrefs([['link' => 'https://fonts.googleapis.com/css2?family=Lato&display=block']], 'https://sito.test')
        === ['https://fonts.googleapis.com/css2?family=Lato&display=block']
);

check('un link assoluto o senza schema resta com\'è', fn () =>
    CssFontLinks::hrefs([['link' => 'https://cdn.test/a.css'], ['link' => '//cdn.test/b.css']], 'https://sito.test')
        === ['https://cdn.test/a.css', '//cdn.test/b.css']
);

check('lo stesso foglio si carica una volta sola; link vuoti e righe storte si saltano', fn () =>
    CssFontLinks::hrefs([['link' => $locale], ['link' => ''], 'x', ['name' => 'Senza link'], ['link' => $locale], ['link' => $google]], 'https://sito.test')
        === ['https://sito.test'.$locale, $google.'&display=swap']
);

check('la testa e le scelte del tema leggono solo i font visibili e non cancellati', function () {
    $root = dirname(__DIR__, 3);
    $lettura = "sqlSelect('css_font', ['visible' => 'true', 'deleted' => 'false'])";

    return str_contains((string) file_get_contents($root.'/app/view/components/frontend/layout/head.php'), $lettura)
        && str_contains((string) file_get_contents($root.'/class/App/Resources/Css/CssDefaultResource.php'), $lettura);
});

summary();
