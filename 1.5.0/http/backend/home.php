<?php

$TITLE = 'Home';
$UPDATE_RESULT = null;

if (
    isset($_POST['run_app_update'])
    && is_array($USER->authority ?? null)
    && in_array('admin', $USER->authority, true)
) {

    $systemUser = infoUser('@system', 'username');
    $systemToken = trim((string) ($systemUser->api_internal_user->token ?? ''));
    $releaseId = trim((string) ($_POST['release_id'] ?? ''));

    if ($systemToken === '') {
        $UPDATE_RESULT = [
            'success' => false,
            'message' => 'Token API di @system non disponibile.',
        ];
    } else {
        $UPDATE_RESULT = curlJson(
            $PATH->api.'/app/update/',
            'POST',
            [
                'release_id' => $releaseId,
                'source' => 'backend',
            ],
            $systemToken
        );

        if (!is_array($UPDATE_RESULT)) {
            $UPDATE_RESULT = [
                'success' => false,
                'message' => 'Risposta update non valida.',
            ];
        }
    }

}

\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/home.php', [
    'TITLE' => $TITLE,
    'UPDATE_RESULT' => $UPDATE_RESULT
])->render();
