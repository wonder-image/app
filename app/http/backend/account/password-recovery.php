<?php

use Wonder\App\PageSchema\AccountPageSchema;

// `verifyUser()` popola `$ALERT` come variabile globale; senza `global` qui,
// il `$ALERT ?? null` letto sotto resterebbe la versione LOCALE (sempre null)
// e l'alert non comparirebbe a video.
global $ALERT;

$TITLE = 'Recupero password';
$VALUES = $_POST;

if (isset($_POST['recovery'])) {
    $VERIFY = verifyUser('username', $_POST['username'], 'backend');

    if ($VERIFY->response) {
        $USER = $VERIFY->user;
        // Token monouso valido 30 minuti; uno nuovo annulla quelli ancora aperti.
        $issued = (new Wonder\Auth\PasswordReset(1800))->issueForUser((int) $USER->id);
        $link = __r('backend.account.password.restore').'?token='.rawurlencode($issued->token);

        $content = "Ecco il link per modificare la tua password.<br>
        <a href='{$link}'>Clicca qui</a><br>
        <br>
        Se non sei stato tu a richiederlo contattaci: marinoni@wonderimage.it";

        if (sendMail('noreply@wonderimage.it', $USER->email, 'Recupero password', $content)) {
            Wonder\Auth\AuthLog::write('password_recovery', (int) $USER->id, 'backend', true);
            header('Location: '.__r('backend.account.login').'?alert=601');
            exit;
        }

        Wonder\Auth\AuthLog::write('password_recovery', (int) $USER->id, 'backend', false, [
            'reason' => 'mail_failed',
        ]);
    } else {
        Wonder\Auth\AuthLog::write('password_recovery', null, 'backend', false, [
            'username' => $_POST['username'] ?? '',
            'alert' => $ALERT ?? null,
        ]);
    }
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-recovery.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'VALUES' => $VALUES,
    '_POST' => $_POST,
    'FORM_SCHEMA' => AccountPageSchema::recoveryFormSchema(),
])->render();
