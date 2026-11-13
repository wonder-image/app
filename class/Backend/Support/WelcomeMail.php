<?php

namespace Wonder\Backend\Support;

use RuntimeException;
use Wonder\App\LegacyGlobals;
use Wonder\App\Support\Mail\MailMessage;
use Wonder\Auth\AuthLog;
use Wonder\Auth\OneTimeToken;

/**
 * La mail di benvenuto di un utente del backend appena creato.
 *
 * Niente credenziali in mail: il nuovo utente sceglie la password dal link
 * `password_set`; se l'invio fallisce resta il recupero password. La usano
 * la gestione utenti e i moduli che creano utenti (es. i commerciali).
 */
final class WelcomeMail
{
    // Validità del link «imposta password».
    public const TOKEN_TTL = 259200;

    /**
     * @param array<string, mixed> $post name, username, email, authority
     * @param mixed $user l'utente restituito da `user()` (oggetto con id, username, email, password)
     */
    public static function send(array $post, mixed $user): bool
    {
        $userId = is_object($user) ? (int) ($user->id ?? 0) : 0;
        $username = trim((string) ($post['username'] ?? ($user->username ?? '')));
        $email = trim((string) ($post['email'] ?? ($user->email ?? '')));
        $name = trim((string) ($post['name'] ?? ''));
        $authorityKey = trim((string) ($post['authority'] ?? ''));

        if ($userId <= 0 || $email === '' || $authorityKey === '') {
            return false;
        }

        $authority = permissions($authorityKey);
        $authorityName = is_object($authority) ? (string) ($authority->name ?? $authorityKey) : $authorityKey;
        $backendPath = (string) LegacyGlobals::get('PATH')->backend;
        $button = ['Accedi', $backendPath];
        $expires = '';

        // Un utente esistente con password continua a usare la sua.
        if (empty($user->password)) {
            try {
                $token = (new OneTimeToken('password_set', self::TOKEN_TTL))->issue($userId);
            } catch (RuntimeException) {
                AuthLog::write('welcome_mail', $userId, 'backend', false, ['reason' => 'token_failed']);

                return false;
            }

            $link = __r('backend.account.password.set').'?token='.rawurlencode($token->token);
            $button = ['Imposta la tua password', $link];
            $expires = 'Il link per impostare la password scade tra '.intdiv(self::TOKEN_TTL, 3600).' ore.';
        }

        $content = MailMessage::make()
            ->heading('Benvenuto nello staff')
            ->text(trim('Ciao '.$name).', benvenuto/a nello staff.')
            ->details(['Username' => $username, 'Autorizzazione' => $authorityName])
            ->button($button[0], $button[1]);

        if ($expires !== '') {
            $content->small($expires);
        }

        $from = (string) ($GLOBALS['SOCIETY']->email ?? '') ?: 'noreply@wonderimage.it';
        $mailSent = (bool) sendMail($from, $email, 'Benvenuto nello staff', (string) $content);
        AuthLog::write('welcome_mail', $userId, 'backend', $mailSent, $mailSent ? [] : ['reason' => 'mail_failed']);

        return $mailSent;
    }
}
