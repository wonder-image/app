<?php

$TITLE = 'Home';
\Wonder\View\View::make($ROOT_APP.'/view/pages/backend/home.php', [
    'TITLE' => $TITLE,
    'NAV_BACKEND' => is_array($NAV_BACKEND ?? null) ? $NAV_BACKEND : [],
    'USER' => is_object($USER ?? null) ? $USER : (object) [ 'authority' => [] ],
])->render();
