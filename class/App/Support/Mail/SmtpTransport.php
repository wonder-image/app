<?php

namespace Wonder\App\Support\Mail;

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use Throwable;

/**
 * Configurazione SMTP condivisa tra `sendMail()` e la verifica delle
 * credenziali dal backend: un solo punto che legge host, porta e accesso
 * da `Credentials::mail()`, così il bottone "Email di prova" controlla
 * esattamente il server con cui il sito spedisce.
 */
final class SmtpTransport
{
    /** Secondi di attesa per la sola verifica: PHPMailer ne aspetterebbe 300. */
    public const CHECK_TIMEOUT = 15;

    public static function configure(PHPMailer $mail, object $credentials): void
    {
        $mail->SMTPDebug = SMTP::DEBUG_OFF;
        $mail->isSMTP();
        $mail->Host = (string) ($credentials->host ?? '');
        $mail->SMTPAuth = true;
        $mail->Username = (string) ($credentials->username ?? '');
        $mail->Password = (string) ($credentials->password ?? '');
        $mail->SMTPSecure = PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = (int) ($credentials->port ?? 0);
    }

    /**
     * Apre la connessione, autentica e chiude. Ritorna '' se tutto va bene,
     * altrimenti il messaggio di errore di PHPMailer (testo del server
     * remoto: da escapare prima di stamparlo).
     */
    public static function check(object $credentials, int $timeout = self::CHECK_TIMEOUT): string
    {
        $mail = new PHPMailer(true);
        self::configure($mail, $credentials);
        $mail->Timeout = max(1, $timeout);

        try {
            $mail->smtpConnect();
            $mail->smtpClose();
        } catch (Throwable $exception) {
            return trim($exception->getMessage()) !== '' ? trim($exception->getMessage()) : 'Connessione SMTP non riuscita';
        }

        return '';
    }
}
