<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\RichText;

\Wonder\View\View::layout('backend.show');

$rows = [];

foreach ((array) ($ITEM ?? []) as $key => $value) {
    $label = (string) ($RESOURCE_CLASS::getLabel((string) $key) ?: $key);
    $display = is_scalar($value)
        ? (string) $value
        : (string) json_encode($value, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

    $rows[] = new RichText(
        '<b>'.htmlspecialchars($label, ENT_QUOTES, 'UTF-8').':</b> '
        .htmlspecialchars($display, ENT_QUOTES, 'UTF-8')
    );
}

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([
        (new Card)->components($rows)->columns(1),
    ])->columns(1)
);

\Wonder\View\View::end();
