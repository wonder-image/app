<?php

namespace Wonder\App\Support\Mail;

use RuntimeException;
use Throwable;
use Wonder\App\Credentials;
use Wonder\App\Table;

/**
 * Prova le credenziali email salvate (`Credentials::mail()`, quindi anche
 * gli override `MAIL_*` del `.env`): con PHPMailer apre e autentica la
 * connessione SMTP, poi spedisce un'email di prova al destinatario; con
 * Brevo spedisce soltanto. Non legge il form: valgono i valori salvati.
 *
 * `$check`, `$send` e `$log` sono iniettabili per i test (niente rete né
 * database): `$check(object $credentials): string` ('' = ok, altrimenti
 * l'errore), `$send(string $to, string $subject, string $body): bool`,
 * `$log(string $recipient, object $credentials, string $error): void`.
 *
 * Un check fallito lascia traccia come un invio fallito: riga `failed` in
 * `mail_log` e voce nel log degli errori (`logCheckFailure()`), perché
 * l'esito non resti soltanto nella notifica a schermo.
 */
final class CredentialsTest
{
    public const SUBJECT = 'Email di prova';

    public static function run(
        string $recipient,
        ?object $credentials = null,
        ?callable $check = null,
        ?callable $send = null,
        ?callable $sendError = null,
        ?callable $log = null
    ): CredentialsTestResult {
        $credentials ??= Credentials::mail();
        $recipient = trim($recipient);
        $service = CredentialsTestResult::service($credentials);
        $host = CredentialsTestResult::host($credentials);
        $username = CredentialsTestResult::username($credentials);

        if ($recipient === '' || filter_var($recipient, FILTER_VALIDATE_EMAIL) === false) {
            return CredentialsTestResult::failure(
                'Destinatario mancante',
                'Il tuo utente non ha un indirizzo email valido: aggiungilo nel profilo e riprova.',
                $credentials
            );
        }

        if ($service === 'brevo') {
            if (trim((string) ($credentials->brevo_api_key ?? '')) === '') {
                return CredentialsTestResult::failure('Brevo non configurato', 'Manca la API key di Brevo.', $credentials);
            }
        } else {
            $missing = [];
            if ($host === '') {
                $missing[] = 'host';
            }
            if ((int) ($credentials->port ?? 0) <= 0) {
                $missing[] = 'porta';
            }
            if ($username === '') {
                $missing[] = 'utente';
            }
            if (trim((string) ($credentials->password ?? '')) === '') {
                $missing[] = 'password';
            }

            if ($missing !== []) {
                return CredentialsTestResult::failure(
                    'Credenziali SMTP incomplete',
                    'Salva prima '.implode(', ', $missing).' del server mail.',
                    $credentials
                );
            }

            $check ??= static fn (object $c): string => SmtpTransport::check($c);
            $error = (string) $check($credentials);

            if ($error !== '') {
                $log ??= static function (string $to, object $c, string $e): void {
                    self::logCheckFailure($to, $c, $e);
                };
                $log($recipient, $credentials, self::target($host, $username, $credentials).': '.$error);

                return CredentialsTestResult::failure(
                    'Connessione SMTP non riuscita',
                    self::target($host, $username, $credentials).': '.$error.' I dettagli sono nel log delle email.',
                    $credentials
                );
            }
        }

        $send ??= static fn (string $to, string $subject, string $body): bool => (bool) sendMail($to, $to, $subject, $body);
        $sendError ??= static fn (): string => function_exists('sendMailError') ? sendMailError() : '';

        $sent = (bool) $send($recipient, self::SUBJECT, self::body($service, $host, $username));

        if (!$sent) {
            $error = trim((string) $sendError());
            $detail = $service === 'brevo'
                ? 'Brevo ha rifiutato l\'invio'
                : 'Connessione a '.self::target($host, $username, $credentials).' riuscita, ma l\'invio è fallito';

            return CredentialsTestResult::failure(
                'Invio non riuscito',
                $detail.($error !== '' ? ': '.$error : '.').' I dettagli sono nel log delle email.',
                $credentials
            );
        }

        $via = $service === 'brevo' ? 'Brevo' : self::target($host, $username, $credentials);

        return CredentialsTestResult::success(
            'Email di prova inviata',
            'Inviata a '.$recipient.' tramite '.$via.'. Controlla la casella (anche lo spam).',
            $credentials
        );
    }

    /**
     * Registra il check SMTP fallito come farebbe `sendMail()` con un invio
     * fallito: riga `failed` in `mail_log` (servizio, destinatario, errore)
     * e voce `[mail] [credentials-test]` nel log errori. Non lancia mai.
     */
    public static function logCheckFailure(string $recipient, object $credentials, string $error): void
    {
        $service = CredentialsTestResult::service($credentials);

        try {
            if (function_exists('__log')) {
                __log(new RuntimeException($error), '['.$service.']', '[credentials-test]', 'ERROR', 'error', [
                    'to' => $recipient,
                    'host' => CredentialsTestResult::host($credentials),
                    'port' => (int) ($credentials->port ?? 0),
                ]);
            }
        } catch (Throwable $exception) {
            // Il log non deve rompere la pagina.
        }

        if (!function_exists('sqlInsert') || !class_exists(Table::class)) {
            return;
        }

        try {
            $values = Table::key('mail_log')->prepare([
                'user_id' => $_SESSION['user_id'] ?? null,
                'from_email' => CredentialsTestResult::username($credentials),
                'reply_to_email' => null,
                'to_email' => $recipient,
                'subject' => self::SUBJECT,
                'template' => 'basic',
                'body_raw' => '',
                'body_text' => '',
                'attachments' => null,
                'service' => $service,
                'status' => 'failed',
                'error_message' => $error,
                'request_uri' => (string) ($_SERVER['REQUEST_URI'] ?? ''),
                'ip' => (string) ($_SERVER['REMOTE_ADDR'] ?? ''),
                'user_agent' => (string) ($_SERVER['HTTP_USER_AGENT'] ?? ''),
            ]);

            sqlInsert('mail_log', $values);
        } catch (Throwable $exception) {
            if (function_exists('__log')) {
                __log($exception, '[mail_log]', '[credentials-test]');
            }
        }
    }

    private static function target(string $host, string $username, object $credentials): string
    {
        $port = (int) ($credentials->port ?? 0);

        return $host.($port > 0 ? ':'.$port : '').($username !== '' ? ' come '.$username : '');
    }

    private static function body(string $service, string $host, string $username): string
    {
        $via = $service === 'brevo' ? 'Brevo' : 'SMTP '.$host.($username !== '' ? ' ('.$username.')' : '');

        return '<p>Questa è un\'email di prova inviata dal backend, pagina Credenziali, il '
            .date('d/m/Y').' alle '.date('H:i').' tramite '.$via.'.</p>'
            .'<p>Se la leggi, le credenziali email salvate funzionano.</p>';
    }
}
