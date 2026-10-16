<?php
use Wonder\Backend\Support\ResourceFormLayoutRenderer as Layout;
use Wonder\Elements\Components\{Card, SectionTitle, Text};
use Wonder\View\View;

View::layout('backend.auth');

echo Layout::renderLayout((new Card())->gap(3)->components([
    SectionTitle::make(__t($titleKey))->level(1)->align('center'),
    Text::make(__t($messageKey))->align('center'),
]));

View::end();
