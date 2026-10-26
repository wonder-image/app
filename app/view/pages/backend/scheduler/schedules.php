<?php

use Wonder\App\Scheduler\Presentation;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.list');

if (!empty($READONLY)) {
    echo ResourceFormLayoutRenderer::renderLayout((new Card())->components([
        '<div class="alert alert-warning mb-0">'.e((string) ($READONLY_NOTICE ?? '')).'</div>',
    ]));
}

echo $TABLE_HTML;
echo Presentation::runAssets();
echo Presentation::cronModal(ROOT);

\Wonder\View\View::end();
