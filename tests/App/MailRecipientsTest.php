<?php
/** php tests/App/MailRecipientsTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\App\Support\Mail\Recipients;

check('una stringa con più indirizzi separati da virgola o punto e virgola diventa una lista', static fn (): bool =>
    Recipients::normalize(' a@example.test, b@example.test;c@example.test ') === ['a@example.test', 'b@example.test', 'c@example.test']
);

check('gli indirizzi non validi e i vuoti spariscono', static fn (): bool =>
    Recipients::normalize(['a@example.test', 'non-email', '', null, ['x'], 'b@example']) === ['a@example.test']
);

check('i duplicati spariscono anche con maiuscole diverse, resta il primo', static fn (): bool =>
    Recipients::normalize(['Ufficio@example.test', 'ufficio@example.test', 'UFFICIO@EXAMPLE.TEST']) === ['Ufficio@example.test']
);

check('null e stringa vuota danno una lista vuota', static fn (): bool =>
    Recipients::normalize(null) === [] && Recipients::normalize('') === [] && Recipients::normalize([]) === []
);

check('il destinatario esce dai CC; destinatario e CC escono dai BCC', static fn (): bool =>
    Recipients::options([
        'cc' => ['Cliente@example.test', 'azienda@example.test', 'contabile@example.test'],
        'bcc' => 'cliente@example.test, azienda@example.test, archivio@example.test',
    ], 'cliente@example.test') === [
        'cc' => ['azienda@example.test', 'contabile@example.test'],
        'bcc' => ['archivio@example.test'],
    ]
);

check('senza opzioni ritorna sempre entrambe le chiavi vuote', static fn (): bool =>
    Recipients::options([], 'cliente@example.test') === ['cc' => [], 'bcc' => []]
);

check('una chiave sconosciuta lancia InvalidArgumentException', static function (): bool {
    try {
        Recipients::options(['ccn' => 'a@example.test'], 'b@example.test');
    } catch (InvalidArgumentException $exception) {
        return str_contains($exception->getMessage(), 'ccn');
    }

    return false;
});

check('format unisce con virgola e spazio, null per lista vuota', static fn (): bool =>
    Recipients::format(['a@example.test', 'b@example.test']) === 'a@example.test, b@example.test'
    && Recipients::format([]) === null
);

summary();
