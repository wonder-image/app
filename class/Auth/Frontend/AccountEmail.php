<?php

namespace Wonder\Auth\Frontend;

use Wonder\App\Models\Contacts\Contact;
use Wonder\Auth\OneTimeToken;
use Wonder\Sql\Transaction;

/** Cambio email: la nuova casella conferma con un link monouso; fino al clic resta valida la vecchia. */
final class AccountEmail
{
    public const PURPOSE = 'email_change';
    private const TTL = 86400;

    /**
     * `$mailer($to, $subject, $body)`; senza, spedisce con `\sendMail`. Un mailer che restituisce `false`
     * non ha spedito: il link appena emesso viene revocato e l'esito è `errors['mail'] = 'send'`.
     * Gli errori sull'email si dicono solo con la password giusta, così non si scopre chi è registrato.
     */
    public static function request(object $user, string $newEmail, string $password, string $confirmUrl, ?callable $mailer = null): object
    {
        $userId = (int) ($user->id ?? 0);
        $email = strtolower(trim($newEmail));
        $row = \sqlSelect('user', ['id' => $userId], 1)->row ?? [];
        if (!\checkPassword($password, (string) ($row['password'] ?? ''))) {
            return (object) ['success' => false, 'errors' => ['current_password' => 'wrong']];
        }
        $errors = [];
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            $errors['email'] = 'invalid';
        } elseif ($email === strtolower((string) ($user->email ?? ''))) {
            $errors['email'] = 'same';
        } elseif (!self::isFree($email, $userId)) {
            $errors['email'] = 'exists';
        }
        if ($errors !== []) {
            return (object) ['success' => false, 'errors' => $errors];
        }

        $tokens = new OneTimeToken(self::PURPOSE, self::TTL);
        $issued = $tokens->issue($userId, $userId, null, ['email' => $email]);
        $url = $confirmUrl.'?token='.rawurlencode($issued->token);
        $mailer ??= static fn (string $to, string $subject, string $body) => \sendMail((string) ($GLOBALS['SOCIETY']->email ?? ''), $to, $subject, $body);
        $sent = $mailer($email, (string) __t('account.email.mail_subject'), (string) __t('account.email.mail_body', ['url' => $url]));
        if ($sent === false) {
            $tokens->revokeOpenForSubject($userId);
            return (object) ['success' => false, 'errors' => ['mail' => 'send']];
        }

        return (object) ['success' => true, 'errors' => []];
    }

    /**
     * Esiti: `confirmed`, `invalid` (link scaduto, usato, inventato o salvataggio non riuscito) e `taken`.
     * Il link si consuma nella stessa transazione del cambio: se il salvataggio non riesce torna indietro anche
     * il consumo e il link resta usabile. Con `taken` il consumo resta: il link si brucia.
     */
    public static function confirm(string $token): string
    {
        $tokens = new OneTimeToken(self::PURPOSE, self::TTL);
        try {
            return Transaction::run(static function () use ($tokens, $token): string {
                $record = $tokens->consume($token);
                $userId = (int) ($record->subject_user_id ?? 0);
                $email = strtolower(trim((string) ($record->metadata['email'] ?? '')));
                if ($record === null || $userId <= 0 || $email === '') {
                    return 'invalid';
                }
                if (!self::isFree($email, $userId)) {
                    return 'taken';
                }
                $GLOBALS['ALERT'] = null;
                $result = \user(['email' => $email, 'area' => 'frontend', 'authority' => 'client'], $userId);
                if (!empty($GLOBALS['ALERT']) || !($result->user->exists ?? false)) {
                    throw new AccountSaveFailed('user');
                }
                if (!(\markUserEmailVerified($userId, date('Y-m-d H:i:s'))->success ?? false)) {
                    throw new AccountSaveFailed('user');
                }
                $contact = Contact::find(['user_id' => $userId], 1);
                if (is_array($contact) && !empty($contact['id'])) {
                    if (!(Contact::update(['email' => $email], (int) $contact['id'])->success ?? false)) {
                        throw new AccountSaveFailed('contact');
                    }
                }

                return 'confirmed';
            });
        } catch (AccountSaveFailed) {
            return 'invalid';
        }
    }

    /** La nuova email che porta il link, senza consumarlo (per la GET); `null` se il link non vale più. */
    public static function pendingEmail(string $token): ?string
    {
        $record = (new OneTimeToken(self::PURPOSE, self::TTL))->inspect($token);
        $email = strtolower(trim((string) ($record->metadata['email'] ?? '')));

        return $record !== null && $record->subject_user_id > 0 && $email !== '' ? $email : null;
    }

    /** Con una password nuova i link di cambio email già mandati non valgono più. */
    public static function revokeOpen(int $userId): void
    {
        (new OneTimeToken(self::PURPOSE, self::TTL))->revokeOpenForSubject($userId);
    }

    /** `\unique` compone l'SQL a mano e un'email valida può avere apostrofi: passa prima da `sanitize`, come in `user()`. */
    private static function isFree(string $email, int $userId): bool
    {
        return \unique(\sanitize($email), 'user', 'email', $userId);
    }
}
