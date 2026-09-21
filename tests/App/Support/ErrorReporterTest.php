<?php
/** php tests/App/Support/ErrorReporterTest.php */
declare(strict_types=1);

require __DIR__ . '/../../../vendor/autoload.php';
require __DIR__ . '/../../harness.php';

use Wonder\App\Support\Errors\ErrorReporter;

$errore = new RuntimeException('Timeout del provider');

check('lo stesso errore ha sempre la stessa impronta', fn () =>
    ErrorReporter::fingerprint('fatture-in-cloud', 'invoice.send', $errore)
    === ErrorReporter::fingerprint('fatture-in-cloud', 'invoice.send', $errore)
);

check('servizio o azione diversi danno impronte diverse', fn () =>
    ErrorReporter::fingerprint('fatture-in-cloud', 'invoice.send', $errore)
        !== ErrorReporter::fingerprint('stripe', 'invoice.send', $errore)
    && ErrorReporter::fingerprint('fatture-in-cloud', 'invoice.send', $errore)
        !== ErrorReporter::fingerprint('fatture-in-cloud', 'invoice.cancel', $errore)
);

check('un errore scritto a mano si riconosce dal testo', fn () =>
    ErrorReporter::fingerprint('corriere', 'tracking', 'risposta vuota')
    === ErrorReporter::fingerprint('corriere', 'tracking', 'risposta vuota')
    && ErrorReporter::fingerprint('corriere', 'tracking', 'risposta vuota')
        !== ErrorReporter::fingerprint('corriere', 'tracking', 'risposta storta')
);

check('l\'impronta è una stringa breve e stabile', function () use ($errore) {
    $impronta = ErrorReporter::fingerprint('servizio', 'azione', $errore);

    return strlen($impronta) === 40 && preg_match('/^[a-f0-9]{40}$/', $impronta) === 1;
});

check('senza risolutore non si sa a chi scrivere', function () {
    ErrorReporter::recipientsUsing(null);

    return ErrorReporter::recipients() === [];
});

check('il risolutore dice a chi scrivere', function () {
    ErrorReporter::recipientsUsing(static fn (): array => ['dev@esempio.it']);

    $destinatari = ErrorReporter::recipients();

    ErrorReporter::recipientsUsing(null);

    return $destinatari === ['dev@esempio.it'];
});

check('gli indirizzi storti vengono scartati', function () {
    ErrorReporter::recipientsUsing(static fn (): array => ['dev@esempio.it', '', 'non-un-indirizzo', 'due@esempio.it']);

    $destinatari = ErrorReporter::recipients();

    ErrorReporter::recipientsUsing(null);

    return $destinatari === ['dev@esempio.it', 'due@esempio.it'];
});

summary();
