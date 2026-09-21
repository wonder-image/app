<?php // tests/Support/NumberFormatTest.php
declare(strict_types=1);

require __DIR__.'/../../vendor/autoload.php';
require __DIR__.'/../harness.php';
require __DIR__.'/../../app/function/string/general.php';

check('un decimale con la virgola resta quel numero', fn () =>
    normalize_number('19,90') === '19.90'
    && normalize_number('4,5') === '4.5'
);

check('un decimale con il punto non si tocca', fn () =>
    normalize_number('24.50') === '24.50'
);

check('vince l\'ultimo separatore', fn () =>
    normalize_number('1.234,50') === '1234.50'
    && normalize_number('1,234.50') === '1234.50'
);

check('tre cifre dopo la virgola sono le migliaia', fn () =>
    normalize_number('1,234') === '1234'
);

check('simboli e spazi non contano', fn () =>
    normalize_number('€ 19,90') === '19.90'
    && normalize_number('22%') === '22'
);

check('il vuoto resta vuoto', fn () =>
    normalize_number('') === '' && normalize_number(null) === ''
);

check('i decimali non si perdono per strada', function () {
    // Prima `create_number()` veniva chiamato due volte: la prima con zero
    // decimali, che arrotondava 24,50 a 25 prima ancora di formattarlo.
    return create_number(normalize_number('24,50'), 2) === '24.50'
        && create_number(normalize_number('19,90'), 2) === '19.90'
        && create_number(normalize_number('4,5'), 2) === '4.50';
});

check('senza decimali si arrotonda, come prima', fn () =>
    create_number(normalize_number('24,50'), 0) === '25'
);

summary();
