<?php
/** php tests/App/Support/MailCredentialsTestTest.php */
declare(strict_types=1);

require __DIR__.'/../../../vendor/autoload.php';
require __DIR__.'/../../harness.php';

use PHPMailer\PHPMailer\PHPMailer;
use Wonder\App\Support\Mail\CredentialsTest;
use Wonder\App\Support\Mail\SmtpTransport;

$smtp = (object) ['service' => 'phpmailer', 'host' => 'smtp.example.test', 'port' => 465, 'username' => 'noreply@example.test', 'password' => 'secret', 'brevo_api_key' => ''];
$brevo = (object) ['service' => 'brevo', 'host' => '', 'port' => null, 'username' => 'noreply@example.test', 'password' => '', 'brevo_api_key' => 'xkeysib-1'];

$noCheck = static fn (): string => throw new RuntimeException('check non atteso');
$noSend = static fn (): bool => throw new RuntimeException('send non atteso');
$noError = static fn (): string => '';

echo "SmtpTransport\n";
check('configure() scrive host, porta, utente e SMTPS su PHPMailer', function () use ($smtp) {
    $mail = new PHPMailer(true);
    SmtpTransport::configure($mail, $smtp);

    return $mail->Host === 'smtp.example.test' && $mail->Port === 465 && $mail->Username === 'noreply@example.test'
        && $mail->Password === 'secret' && $mail->SMTPAuth === true && $mail->SMTPSecure === PHPMailer::ENCRYPTION_SMTPS
        && $mail->Mailer === 'smtp';
});
check('check() su un host inesistente torna un errore (non lancia) entro il timeout', function () {
    $start = microtime(true);
    $error = SmtpTransport::check((object) ['host' => 'smtp.invalid', 'port' => 1, 'username' => 'a', 'password' => 'b'], 2);

    return $error !== '' && (microtime(true) - $start) < 20;
});

echo "CredentialsTest\n";
check('destinatario vuoto → fallisce senza toccare rete né invio', function () use ($smtp, $noCheck, $noSend, $noError) {
    $r = CredentialsTest::run('', $smtp, $noCheck, $noSend, $noError);

    return !$r->ok && $r->title === 'Destinatario mancante';
});
check('SMTP incompleto → elenca i campi mancanti, niente connessione', function () use ($noCheck, $noSend, $noError) {
    $r = CredentialsTest::run('me@example.test', (object) ['service' => 'phpmailer', 'host' => '', 'port' => 0, 'username' => 'x', 'password' => ''], $noCheck, $noSend, $noError);

    return !$r->ok && $r->title === 'Credenziali SMTP incomplete' && str_contains($r->message, 'host') && str_contains($r->message, 'porta') && str_contains($r->message, 'password') && !str_contains($r->message, 'utente');
});
check('connessione SMTP fallita → errore del server nel messaggio, nessun invio', function () use ($smtp, $noSend, $noError) {
    $r = CredentialsTest::run('me@example.test', $smtp, static fn (): string => 'SMTP Error: Could not authenticate.', $noSend, $noError);

    return !$r->ok && $r->title === 'Connessione SMTP non riuscita' && str_contains($r->message, 'smtp.example.test:465') && str_contains($r->message, 'Could not authenticate');
});
check('connessione ok e invio ok → successo con destinatario e host', function () use ($smtp, $noError) {
    $sent = [];
    $r = CredentialsTest::run('me@example.test', $smtp, static fn (): string => '', static function (string $to, string $subject, string $body) use (&$sent): bool {
        $sent = [$to, $subject, $body];

        return true;
    }, $noError);

    return $r->ok && $sent[0] === 'me@example.test' && $sent[1] === CredentialsTest::SUBJECT && str_contains($sent[2], 'smtp.example.test')
        && str_contains($r->message, 'me@example.test') && str_contains($r->message, 'smtp.example.test') && $r->host === 'smtp.example.test';
});
check('connessione ok ma invio fallito → riporta l\'errore di sendMail', function () use ($smtp) {
    $r = CredentialsTest::run('me@example.test', $smtp, static fn (): string => '', static fn (): bool => false, static fn (): string => 'Recipient refused');

    return !$r->ok && $r->title === 'Invio non riuscito' && str_contains($r->message, 'riuscita') && str_contains($r->message, 'Recipient refused');
});
check('Brevo senza API key → fallisce prima dell\'invio', function () use ($brevo, $noCheck, $noSend, $noError) {
    $r = CredentialsTest::run('me@example.test', (object) array_merge((array) $brevo, ['brevo_api_key' => '']), $noCheck, $noSend, $noError);

    return !$r->ok && $r->title === 'Brevo non configurato';
});
check('Brevo → nessun check SMTP, invia e riporta "Brevo"', function () use ($brevo, $noCheck, $noError) {
    $r = CredentialsTest::run('me@example.test', $brevo, $noCheck, static fn (): bool => true, $noError);

    return $r->ok && $r->service === 'brevo' && str_contains($r->message, 'Brevo');
});

echo "sendMail\n";
check('sendMailError() esiste e sendMail usa SmtpTransport::configure', function () {
    $src = (string) file_get_contents(__DIR__.'/../../../app/function/mail.php');

    return str_contains($src, 'function sendMailError()') && str_contains($src, 'SmtpTransport::configure($MAIL, $CREDENTIALS)') && !str_contains($src, '$MAIL->isSMTP()');
});

summary();
