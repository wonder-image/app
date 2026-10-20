<?php

use Wonder\App\PageSchema\AccountPageSchema;
use Wonder\Backend\Support\ResourcePageController;
use Wonder\Http\Csrf;

// `verifyUser()` popola `$ALERT` come variabile globale; senza `global` qui,
// il `$ALERT ?? null` letto sotto resterebbe la versione LOCALE (sempre null)
// e l'alert non comparirebbe a video.
global $ALERT;

$TITLE = 'Recupero password';
$VALUES = $_POST;

$RECOVERY_IDENTIFIER = Wonder\Auth\LoginThrottle::identifier((string) ($_POST['username'] ?? ''));

if (isset($_POST['recovery']) && !Csrf::verify()) {
    $ALERT = ResourcePageController::CSRF_ALERT;
} elseif (isset($_POST['recovery']) && !Wonder\Auth\LoginThrottle::check('recovery', $RECOVERY_IDENTIFIER, Wonder\Http\ClientIp::resolve())) {
    $ALERT = Wonder\Auth\LoginThrottle::ALERT;
    Wonder\Auth\LoginThrottle::sendRetryAfter();
    Wonder\Auth\AuthLog::write('recovery_throttled', null, 'backend', false, ['identifier' => $RECOVERY_IDENTIFIER]);
} elseif (isset($_POST['recovery'])) {
    $VERIFY = verifyUser('username', $_POST['username'], 'backend');

    // Risposta 601 uniforme: non rivela se l'utente esiste o se la mail è partita.
    if ($VERIFY->response) {
        $USER = $VERIFY->user;
        // Token monouso valido 30 minuti; uno nuovo annulla quelli ancora aperti.
        $issued = (new Wonder\Auth\PasswordReset(1800))->issueForUser((int) $USER->id);
        $link = __r('backend.account.password.restore').'?token='.rawurlencode($issued->token);

        $content = "Ecco il link per modificare la tua password.<br>
        <a href='{$link}'>Clicca qui</a><br>
        <br>
        Se non sei stato tu a richiederlo contattaci: marinoni@wonderimage.it";

        $from = (string) ($GLOBALS['SOCIETY']->email ?? '') ?: 'noreply@wonderimage.it';

        if (sendMail($from, $USER->email, 'Recupero password', $content)) {
            Wonder\Auth\AuthLog::write('password_recovery', (int) $USER->id, 'backend', true, ['identifier' => $RECOVERY_IDENTIFIER]);
        } else {
            Wonder\Auth\AuthLog::write('password_recovery', (int) $USER->id, 'backend', false, [
                'reason' => 'mail_failed',
                'identifier' => $RECOVERY_IDENTIFIER,
            ]);
        }
    } else {
        Wonder\Auth\AuthLog::write('recovery_unknown', null, 'backend', false, [
            'identifier' => $RECOVERY_IDENTIFIER,
            'alert' => $ALERT ?? null,
        ]);
    }

    $ALERT = null;
    header('Location: '.__r('backend.account.login').'?alert=601');
    exit;
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-recovery.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'VALUES' => $VALUES,
    '_POST' => $_POST,
    'FORM_SCHEMA' => AccountPageSchema::recoveryFormSchema(),
])->render();
