<?php

namespace Wonder\App\Support\Mail;

use Wonder\App\Credentials;

/**
 * Prova le credenziali email salvate (`Credentials::mail()`, quindi anche
 * gli override `MAIL_*` del `.env`): con PHPMailer apre e autentica la
 * connessione SMTP, poi spedisce un'email di prova al destinatario; con
 * Brevo spedisce soltanto. Non legge il form: valgono i valori salvati.
 *
 * `$check` e `$send` sono iniettabili per i test (niente rete):
 * `$check(object $credentials): string` ('' = ok, altrimenti l'errore),
 * `$send(string $to, string $subject, string $body): bool`.
 */
final class CredentialsTest
{
    public const SUBJECT = 'Email di prova';

    public static function run(
        string $recipient,
        ?object $credentials = null,
        ?callable $check = null,
        ?callable $send = null,
        ?callable $sendError = null
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
                return CredentialsTestResult::failure(
                    'Connessione SMTP non riuscita',
                    self::target($host, $username, $credentials).': '.$error,
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
