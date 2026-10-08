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

    /** `$mailer($to, $subject, $body)`; senza, spedisce con `\sendMail`. */
    public static function request(object $user, string $newEmail, string $password, string $confirmUrl, ?callable $mailer = null): object
    {
        $userId = (int) ($user->id ?? 0);
        $email = strtolower(trim($newEmail));
        $row = \sqlSelect('user', ['id' => $userId], 1)->row ?? [];
        $errors = [];
        if (!\checkPassword($password, (string) ($row['password'] ?? ''))) {
            $errors['current_password'] = 'wrong';
        }
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

        $issued = (new OneTimeToken(self::PURPOSE, self::TTL))->issue($userId, $userId, null, ['email' => $email]);
        $url = $confirmUrl.'?token='.rawurlencode($issued->token);
        $mailer ??= static fn (string $to, string $subject, string $body) => \sendMail((string) ($GLOBALS['SOCIETY']->email ?? ''), $to, $subject, $body);
        $mailer($email, (string) __t('account.email.mail_subject'), (string) __t('account.email.mail_body', ['url' => $url]));

        return (object) ['success' => true, 'errors' => []];
    }

    public static function confirm(string $token): string
    {
        $record = (new OneTimeToken(self::PURPOSE, self::TTL))->consume($token);
        $userId = (int) ($record->subject_user_id ?? 0);
        $email = strtolower(trim((string) ($record->metadata['email'] ?? '')));
        if ($record === null || $userId <= 0 || $email === '') {
            return 'invalid';
        }
        if (!self::isFree($email, $userId)) {
            return 'taken';
        }
        try {
            Transaction::run(static function () use ($userId, $email): void {
                $GLOBALS['ALERT'] = null;
                $result = \user(['email' => $email, 'area' => 'frontend', 'authority' => 'client'], $userId);
                if (!empty($GLOBALS['ALERT']) || !($result->user->exists ?? false)) {
                    throw new AccountSaveFailed('user');
                }
                \markUserEmailVerified($userId, date('Y-m-d H:i:s'));
                $contact = Contact::find(['user_id' => $userId], 1);
                if (is_array($contact) && !empty($contact['id'])) {
                    if (!(Contact::update(['email' => $email], (int) $contact['id'])->success ?? false)) {
                        throw new AccountSaveFailed('contact');
                    }
                }
            });
        } catch (AccountSaveFailed) {
            return 'invalid';
        }
        return 'confirmed';
    }

    /** `\unique` compone l'SQL a mano e un'email valida può avere apostrofi: passa prima da `sanitize`, come in `user()`. */
    private static function isFree(string $email, int $userId): bool
    {
        return \unique(\sanitize($email), 'user', 'email', $userId);
    }
}
