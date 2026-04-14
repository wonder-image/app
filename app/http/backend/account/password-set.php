<?php

$TITLE = 'Imposta password';
$VALUES = $_POST;
$restriction = $_GET['r'] ?? '';

if ($restriction === '') {
    header('Location: '.__r('backend.account.login').'?alert=913');
    exit;
}

$restrictionData = json_decode((string) base64_decode($restriction));

if (
    !is_object($restrictionData)
    || empty($restrictionData->user_id)
) {
    header('Location: '.__r('backend.account.login').'?alert=913');
    exit;
}

$USER_ID = (int) $restrictionData->user_id;

if (isset($_POST['set-password'])) {
    $password = hashPassword($_POST['password']);

    if (empty($ALERT)) {
        $VERIFY = verifyUser('id', $USER_ID, 'backend');

        if ($VERIFY->response) {
            $USER = $VERIFY->user;

            if (empty($USER->password)) {
                sqlModify('user', ['password' => $password], 'id', $USER_ID);

                $content = "La tua password è stata impostata con successo! <br>
                <a href='".__r('backend.account.login')."'>Accedi</a><br>
                <br>
                Se non sei stato tu a richiederlo contattaci: info@wonderimage.it";

                if (sendMail('noreply@wonderimage.it', $USER->email, 'Password impostata', $content)) {
                    Wonder\Auth\AuthLog::write('password_set', (int) $USER->id, 'backend', true);
                    header('Location: '.__r('backend.account.login').'?alert=611');
                    exit;
                }

                Wonder\Auth\AuthLog::write('password_set', (int) $USER->id, 'backend', false, [
                    'reason' => 'mail_failed',
                ]);
            } else {
                $ALERT = 916;
            }
        }
    }
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/password-set.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'VALUES' => $VALUES,
    '_POST' => $_POST,
    'RESTRICTION' => $restriction,
])->render();
