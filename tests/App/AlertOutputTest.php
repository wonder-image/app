<?php
/** php tests/App/AlertOutputTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
require __DIR__ . '/../../app/function/components/alert.php';

$render = static function (mixed $alert, array $get = []): string {
    $GLOBALS['ALERT'] = $alert;
    $_GET = $get;
    ob_start();
    alert();

    return (string) ob_get_clean();
};

check('codice numerico nello script', fn () =>
    $render(651) === 'alertToast(651);'
    && $render('', ['alert' => '651']) === 'alertToast(651);'
);

check('testo del form e query string non entrano nello script', fn () =>
    $render("Orari, riga 1: la chiusura deve essere dopo l'apertura.") === ''
    && $render('', ['alert' => "1);alert(document.cookie);//"]) === ''
    && $render(null) === ''
);

summary();
