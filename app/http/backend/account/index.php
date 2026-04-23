<?php

use Wonder\App\PageSchema\AccountPageSchema;

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

if (isset($_POST['modify'])) {
    $password = sanitize($_POST['password']);

    if (checkPassword($password, $VALUES['password'])) {
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

if (isset($_POST['modify-password'])) {
    $oldPassword = sanitize($_POST['old-password']);

    if (checkPassword($oldPassword, $VALUES['password'])) {
        $newPassword = hashPassword($_POST['new-password']);
        sqlModify('user', ['password' => $newPassword], 'id', $USER->id);

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
    'COLOR_OPTIONS' => $COLOR_OPTIONS,
    'PROFILE_FORM_SCHEMA' => AccountPageSchema::profileFormSchema($COLOR_OPTIONS),
    'PASSWORD_FORM_SCHEMA' => AccountPageSchema::passwordFormSchema(),
])->render();
