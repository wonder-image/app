<?php

\Wonder\View\View::layout('backend.form');
$saveBar = empty($READONLY) || !empty($READONLY_EDITABLE);
echo \Wonder\Backend\Support\ResourceFormLayoutRenderer::render($FORM_LAYOUT, [
    'id' => 'resource-layout-form',
    'method' => $FORM_METHOD ?? 'POST',
    'action' => $FORM_ACTION ?? '',
    'onsubmit' => $saveBar ? 'loadingSpinner()' : 'return false',
    'attributes' => ['data-wi-save-bar' => $saveBar, 'data-wi-save-bar-dirty' => $saveBar && !empty($FORM_ERRORS)],
]);
\Wonder\View\View::end();
