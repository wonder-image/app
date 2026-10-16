<?php

use Wonder\App\Scheduler\Presentation;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.list');

if (!empty($READONLY)) {
    echo (new Card())->columnSpan(12)->components([
        '<div class="alert alert-warning mb-0">'.e((string) ($READONLY_NOTICE ?? '')).'</div>',
    ])->render('bootstrap');
}

echo $TABLE_HTML;
echo Presentation::runAssets();
echo Presentation::cronModal(ROOT);

\Wonder\View\View::end();
