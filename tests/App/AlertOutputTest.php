<?php
/** php tests/App/AlertOutputTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';
require __DIR__ . '/../../app/function/components/alert.php';

use Wonder\App\Theme;
use Wonder\Frontend\Support\FlashMessage;

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

$_SESSION = [];
Theme::set('wonder');

check('FlashMessage conserva testo, titolo e livello', function (): bool {
    FlashMessage::success('Quantità aggiornata.', 'Carrello aggiornato');

    return FlashMessage::pull() === [
        'level' => 'success',
        'title' => 'Carrello aggiornato',
        'message' => 'Quantità aggiornata.',
    ];
});

check('FlashMessage viene consumato una sola volta', function (): bool {
    FlashMessage::error('Dati non validi.', 'Controlla i dati');
    FlashMessage::pull();

    return FlashMessage::pull() === [] && !isset($_SESSION[FlashMessage::KEY]);
});

check('FlashMessage usa Alert ed escapa titolo e testo', function (): bool {
    FlashMessage::error('<script>alert(1)</script>', 'Errore <grave>');
    $html = FlashMessage::render();

    return str_contains($html, 'wi-alert wi-show')
        && str_contains($html, 'Errore &lt;grave&gt;')
        && str_contains($html, '&lt;script&gt;alert(1)&lt;/script&gt;')
        && !str_contains($html, '<script>');
});

check('FlashMessage ignora il testo vuoto e rifiuta livelli sconosciuti', function (): bool {
    FlashMessage::info('Messaggio precedente.');
    FlashMessage::warning('   ', 'Ignorato');
    $preserved = FlashMessage::pull()['message'] === 'Messaggio precedente.';

    try {
        FlashMessage::put('Testo', 'danger');
    } catch (\InvalidArgumentException) {
        return $preserved;
    }

    return false;
});

check('il layout frontend base include il renderer FlashMessage', fn (): bool =>
    str_contains(
        (string) file_get_contents(__DIR__.'/../../app/view/layout/frontend/base.php'),
        "View::component('frontend.layout.flash-message')"
    )
);

summary();
