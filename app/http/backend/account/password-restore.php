<?php

use Wonder\App\PageSchema\AccountPageSchema;

$TITLE = 'Cambio password';
$VALUES = $_POST;
$time = strtotime('now');
$restriction = $_GET['r'] ?? '';
$USER = null;
$validity = null;

if ($restriction === '') {
    header('Location: '.__r('backend.account.login').'?alert=913');
    exit;
}

$restrictionData = json_decode((string) base64_decode($restriction));

if (
    !is_object($restrictionData)
    || empty($restrictionData->user_id)
    || empty($restrictionData->validity)
) {
    header('Location: '.__r('backend.account.login').'?alert=913');
    exit;
}

$USER = infoUser($restrictionData->user_id);
$validity = (int) $restrictionData->validity;

if ($validity <= $time) {
    $ALERT = 914;
}

if (isset($_POST['restore'])) {
    $password = hashPassword($_POST['password']);

    if ($validity <= $time) {
        $ALERT = 914;
    }

    if (empty($ALERT)) {
        $VERIFY = verifyUser('id', $USER->id, 'backend');

        if ($VERIFY->response) {
            $USER = $VERIFY->user;

            sqlModify('user', ['password' => $password], 'id', $USER->id);

            $content = "La tua password è stata modificata con successo! <br>
            <br>
            È possibile cambiare la tua password in qualsiasi momento in Login -> Account -> Modifica password oppure premi <br><a href='".__r('backend.account.index')."'>qui</a><br>
            <br>
            Se non sei stato tu a richiederlo contattaci: marinoni@wonderimage.it";

            if (sendMail('noreply@wonderimage.it', $USER->email, 'Password modificata', $content)) {
                Wonder\Auth\AuthLog::write('password_reset', (int) $USER->id, 'backend', true);
                header('Location: '.__r('backend.account.login').'?alert=602');
                exit;
            }

            Wonder\Auth\AuthLog::write('password_reset', (int) $USER->id, 'backend', false, [
                'reason' => 'mail_failed',
            ]);
        }
    }
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-restore.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'USER' => $USER,
    'VALUES' => $VALUES,
    '_POST' => $_POST,
    'RESTRICTION' => $restriction,
    'FORM_SCHEMA' => AccountPageSchema::restoreFormSchema(),
])->render();
