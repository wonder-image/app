<?php

use Wonder\App\PageSchema\AccountPageSchema;

// `user()` (handler upload) popola `$ALERT` come variabile globale; senza
// `global` qui, gli `$ALERT = ...` impostati nel ramo `modify` /
// `modify-password` resterebbero locali e il `$ALERT ?? null` passato a
// View::make sarebbe quello, MA `alert()` in body-end legge `global $ALERT`
// e quindi vedrebbe valori spuri propagati per side-effect dalle helper
// chiamate qui sopra. Allineiamo le due viste con `global $ALERT;`.
global $ALERT;

$TITLE = 'Account';
$VALUES = sqlSelect('user', ['id' => $USER->id], 1)->row;
$COLOR_OPTIONS = [];
$NAME = (object) [
    'table' => 'user',
    'folder' => 'user',
];

\Wonder\App\LegacyGlobals::set('NAME', $NAME);
$GLOBALS['NAME'] = $NAME;

foreach ($DEFAULT->colorUser as $key => $color) {
    if (!empty($color['active'])) {
        $COLOR_OPTIONS[$key] = $color['name'];
    }
}

// Senza token CSRF valido nessuno dei due form scrive.
$CSRF_VALID = \Wonder\Http\Csrf::verify();

if (!$CSRF_VALID && (isset($_POST['modify']) || isset($_POST['modify-password']))) {
    $ALERT = \Wonder\Backend\Support\ResourcePageController::CSRF_ALERT;
}

if ($CSRF_VALID && isset($_POST['modify'])) {
    $password = (string) ($_POST['password'] ?? '');

    if (checkUserPassword($password, $VALUES['password'])) {
        $POST = array_merge($_POST, $_FILES);
        $UPLOAD = user($POST, $USER->id);
        $VALUES = $UPLOAD->values;

        if (empty($ALERT)) {
            $ALERT = 604;
        }
    } else {
        $ALERT = 905;
    }
}

if ($CSRF_VALID && isset($_POST['modify-password'])) {
    $oldPassword = (string) ($_POST['old-password'] ?? '');
    $rawNewPassword = (string) ($_POST['new-password'] ?? '');

    // Password nuova vuota o di soli spazi: rifiutata lato server.
    if (trim($rawNewPassword) === '') {
        $ALERT = 977;
    } elseif (checkUserPassword($oldPassword, $VALUES['password'])) {
        $newPassword = hashPassword($rawNewPassword);
        $saved = sqlModify('user', ['password' => $newPassword], 'id', $USER->id);

        // Una sessione o un cookie "ricordami" rubati prima del cambio non restano validi.
        if ($saved->success ?? false) {
            Wonder\Auth\RememberMe::revokeUser((int) $USER->id);
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                session_regenerate_id(true);
            }
        }

        if (empty($ALERT)) {
            $ALERT = 603;
        }
    } else {
        $ALERT = 905;
    }
}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/account/index.php', [
    'TITLE' => $TITLE,
    'ALERT' => $ALERT ?? null,
    'VALUES' => $VALUES,
    'PROFILE_DIRTY' => isset($_POST['modify']) && isset($UPLOAD) && empty($UPLOAD->written),
    'COLOR_OPTIONS' => $COLOR_OPTIONS,
    'PROFILE_FORM_SCHEMA' => AccountPageSchema::profileFormSchema($COLOR_OPTIONS),
    'PASSWORD_FORM_SCHEMA' => AccountPageSchema::passwordFormSchema(),
])->render();
