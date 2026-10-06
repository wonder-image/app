<?php

use Wonder\App\PageSchema\AccountPageSchema;
use Wonder\Auth\OneTimeToken;
use Wonder\Auth\PasswordReset;

// `verifyUser()` popola `$ALERT` come variabile globale; allinea i write/read
// fatti in questo file con il global così `alert()` in body-end vede gli
// stessi valori e il messaggio compare a video.
global $ALERT;

$TITLE = 'Cambio password';
$VALUES = $_POST;

// Il link dell'email porta un token monouso; il form non ha action, quindi
// il token resta nella query string anche al POST.
$token = (string) ($_POST['token'] ?? $_GET['token'] ?? '');
$record = $token === '' ? null : (new OneTimeToken('password_reset'))->inspect($token);
$VERIFY = $record === null ? null : verifyUser('id', $record->subject_user_id, 'backend');

if (!($VERIFY->response ?? false)) {
    header('Location: '.__r('backend.account.login').'?alert=913');
    exit;
}

$USER = $VERIFY->user;

if (isset($_POST['restore']) && trim((string) ($_POST['password'] ?? '')) !== '') {
    $result = (new PasswordReset())->reset($token, (string) $_POST['password']);

    if (!($result->success ?? false)) {
        header('Location: '.__r('backend.account.login').'?alert=913');
        exit;
    }

    $content = "La tua password è stata modificata con successo! <br>
    <br>
    È possibile cambiare la tua password in qualsiasi momento in Login -> Account -> Modifica password oppure premi <br><a href='".__r('backend.account.index')."'>qui</a><br>
    <br>
    Se non sei stato tu a richiederlo contattaci: marinoni@wonderimage.it";

    $mailSent = sendMail('noreply@wonderimage.it', $USER->email, 'Password modificata', $content);
    Wonder\Auth\AuthLog::write('password_reset', (int) $USER->id, 'backend', true, $mailSent ? [] : [
        'reason' => 'mail_failed',
    ]);

    header('Location: '.__r('backend.account.login').'?alert=602');
    exit;
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-restore.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'USER' => $USER,
    'VALUES' => $VALUES,
    '_POST' => $_POST,
    'FORM_SCHEMA' => AccountPageSchema::restoreFormSchema(),
])->render();
