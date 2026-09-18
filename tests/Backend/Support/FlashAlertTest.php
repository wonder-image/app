<?php
/** php tests/Backend/Support/FlashAlertTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

require __DIR__ . '/../../../app/function/components/alert.php';

use Wonder\Backend\Support\FlashAlert;

$_SESSION = [];
$_GET = [];

check('senza avviso in coda non esce nessuno script', fn () =>
    FlashAlert::script() === ''
);

check('un codice diventa una chiamata a alertToast', function () {
    FlashAlert::code(650);

    return FlashAlert::script() === 'alertToast(650);';
});

check('l\'avviso si consuma: la seconda lettura è vuota', function () {
    FlashAlert::code(650);
    FlashAlert::script();

    return FlashAlert::script() === '' && !isset($_SESSION['wi_flash_alert']);
});

check('un codice non numerico viene scartato', function () {
    FlashAlert::code('650; alert(1)');

    return FlashAlert::script() === '';
});

check('il messaggio scritto a mano passa titolo, testo e livello', function () {
    FlashAlert::custom('Modificato', 'Sbloccate: Ordini.', 'success');

    return FlashAlert::script() === 'alertToast("custom", "success", "Modificato", "Sbloccate: Ordini.");';
});

check('virgolette e tag non possono rompere lo script', function () {
    FlashAlert::custom('"</script>', "Riga'uno", 'warning');
    $script = FlashAlert::script();

    return !str_contains($script, '</script>')
        && !str_contains($script, "'uno")
        && str_starts_with($script, 'alertToast("custom", "warning", ');
});

check('un livello sconosciuto ripiega su success', function () {
    FlashAlert::custom('Titolo', 'Testo', 'esplosione');

    return str_contains(FlashAlert::script(), '"success"');
});

check('fuori dal backend il messaggio scritto a mano non viene emesso', function () {
    FlashAlert::custom('Modificato', 'Sbloccate: Ordini.');

    return FlashAlert::script(false) === '' && FlashAlert::script() === '';
});

check('fuori dal backend i codici escono lo stesso', function () {
    FlashAlert::code(650);

    return FlashAlert::script(false) === 'alertToast(650);';
});

check('saved() usa il titolo del codice 650', function () {
    FlashAlert::saved('Sbloccate: Ordini.');

    return FlashAlert::script() === 'alertToast("custom", "success", "Modificato", "Sbloccate: Ordini.");';
});

check('il body-end del backend stampa anche l\'avviso in coda', function () {
    $GLOBALS['ALERT'] = '';
    FlashAlert::saved('Fatto.');

    ob_start();
    alert('backend');

    return ob_get_clean() === 'alertToast("custom", "success", "Modificato", "Fatto.");';
});

check('nel frontend passa solo il codice', function () {
    $GLOBALS['ALERT'] = '';
    FlashAlert::custom('Modificato', 'Fatto.');
    FlashAlert::code(650);

    ob_start();
    alert();

    return ob_get_clean() === 'alertToast(650);';
});

check('codice in coda e $ALERT convivono', function () {
    $GLOBALS['ALERT'] = '905';
    FlashAlert::saved('Fatto.');

    ob_start();
    alert('backend');
    $script = ob_get_clean();

    return str_starts_with($script, 'alertToast(905);')
        && str_contains($script, 'alertToast("custom"');
});

summary();
