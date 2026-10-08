<?php
/**
 * I campi numerici del tema Wonder accendono da soli AutoNumeric: il
 * frontend non lo carica, il backend sì.
 *
 *   php tests/Themes/WonderAutonumericTest.php
 */
declare(strict_types=1);

if (!defined('APP_URL')) { define('APP_URL', 'http://127.0.0.1:8090'); }
if (!defined('ASSETS_VERSION')) { define('ASSETS_VERSION', 'dev'); }

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Dependencies;
use Wonder\Elements\Form\Components\InputNumber;
use Wonder\Elements\Form\Components\InputPercentige;
use Wonder\Elements\Form\Components\InputPrice;
use Wonder\Elements\Form\Components\InputText;

echo "WonderAutonumeric\n";

foreach ([
    'InputPrice' => static fn () => (new InputPrice('price'))->label('Prezzo')->value('1299.9'),
    'InputNumber' => static fn () => (new InputNumber('qty'))->label('Quantità'),
    'InputPercentige' => static fn () => (new InputPercentige('vat'))->label('IVA'),
] as $name => $make) {
    check("{$name}: il render Wonder attiva AutoNumeric", function () use ($make) {
        Dependencies::reset();
        $html = $make()->render('wonder');

        return in_array('autonumeric', Dependencies::active(), true) && $html !== '';
    });
}

check('un campo di testo non carica AutoNumeric', function () {
    Dependencies::reset();
    (new InputText('name'))->label('Nome')->render('wonder');

    return !in_array('autonumeric', Dependencies::active(), true);
});

check('il render Bootstrap non tocca le dipendenze (le carica l\'area backend)', function () {
    Dependencies::reset();
    (new InputPrice('price'))->label('Prezzo')->render('bootstrap');

    return !in_array('autonumeric', Dependencies::active(), true);
});

summary();
