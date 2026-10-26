<?php

use Wonder\App\Scheduler\Presentation;
use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Card;

\Wonder\View\View::layout('backend.list');

if (!empty($READONLY)) {
    echo ResourceFormLayoutRenderer::renderLayout((new Card())->components([
        Alert::make((string) ($READONLY_NOTICE ?? ''), 'warning')->inline()->addClass('mb-0')->render(),
    ]));
}

echo $TABLE_HTML;
echo Presentation::runAssets();
echo Presentation::cronModal(ROOT);

\Wonder\View\View::end();
