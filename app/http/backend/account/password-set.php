<?php

use Wonder\App\PageSchema\AccountPageSchema;
use Wonder\Auth\OneTimeToken;
use Wonder\Auth\RememberMe;
use Wonder\Sql\Transaction;

// `verifyUser()` popola `$ALERT` come variabile globale; allinea i write/read
// fatti in questo file con il global così `alert()` in body-end vede gli
// stessi valori e il messaggio compare a video.
global $ALERT;

$TITLE = 'Imposta password';
$VALUES = $_POST;

// Il link porta un token monouso di scopo `password_set`, emesso con
// `(new OneTimeToken('password_set', $ttl))->issue($userId)`; il form non ha
// action, quindi il token resta nella query string anche al POST.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$tokens = new OneTimeToken('password_set');
$record = $token === '' ? null : $tokens->inspect($token);
$VERIFY = $record === null ? null : verifyUser('id', $record->subject_user_id, 'backend');

if (!($VERIFY->response ?? false)) {
    header('Location: '.__r('backend.account.login').'?alert=913');
    exit;
}

$USER = $VERIFY->user;

if (isset($_POST['set-password']) && trim((string) ($_POST['password'] ?? '')) !== '') {
    if (!empty($USER->password)) {
        $ALERT = 916;
    } else {
        $password = hashPassword((string) $_POST['password']);

        try {
            $saved = Transaction::run(static function () use ($tokens, $token, $password, $USER): bool {
                if ($tokens->consume($token) === null) {
                    return false;
                }

                $update = sqlModify('user', ['password' => $password], 'id', $USER->id);

                if (!($update->success ?? false)) {
                    throw new RuntimeException('password_set_update_failed');
                }

                RememberMe::revokeUser((int) $USER->id);

                return true;
            });
        } catch (RuntimeException $exception) {
            if ($exception->getMessage() !== 'password_set_update_failed') {
                throw $exception;
            }

            $saved = false;
        }

        if (!$saved) {
            header('Location: '.__r('backend.account.login').'?alert=913');
            exit;
        }

        $content = "La tua password è stata impostata con successo! <br>
        <a href='".__r('backend.account.login')."'>Accedi</a><br>
        <br>
        Se non sei stato tu a richiederlo contattaci: info@wonderimage.it";

        $mailSent = sendMail('noreply@wonderimage.it', $USER->email, 'Password impostata', $content);
        Wonder\Auth\AuthLog::write('password_set', (int) $USER->id, 'backend', true, $mailSent ? [] : [
            'reason' => 'mail_failed',
        ]);

        header('Location: '.__r('backend.account.login').'?alert=611');
        exit;
    }
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-set.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'VALUES' => $VALUES,
    '_POST' => $_POST,
    'FORM_SCHEMA' => AccountPageSchema::setPasswordFormSchema(),
])->render();
