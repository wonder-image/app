<?php

\Wonder\View\View::layout('backend.form');
echo \Wonder\Backend\Support\ResourceFormLayoutRenderer::render($FORM_LAYOUT, [
    'id' => 'resource-layout-form',
    'method' => $FORM_METHOD ?? 'POST',
    'action' => $FORM_ACTION ?? '',
    'onsubmit' => !empty($READONLY) ? 'return false' : 'loadingSpinner()',
]);
\Wonder\View\View::end();
