<?php

/**
 * Un contenitore reso con un tema esplicito rende i figli con lo stesso tema,
 * qualunque sia quello attivo.
 *
 *   php tests/Themes/ChildThemeTest.php
 */

declare(strict_types=1);

use Wonder\App\Theme;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\RichText;

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

echo "ChildTheme\n";

check('una Card resa in bootstrap rende i figli in bootstrap anche col tema attivo wonder', function () {
    Theme::set('wonder');

    $html = (new Card)->components([
        RichText::make('<b>testo</b>')->tag('div'),
        Button::to('/x', 'Apri'),
    ])->render('bootstrap');

    return str_contains($html, '<b>testo</b>') && str_contains($html, 'text-decoration-none btn-primary');
});

check('un ButtonGroup reso in wonder rende i figli in wonder anche col tema attivo bootstrap', function () {
    Theme::set('bootstrap');

    try {
        $html = ButtonGroup::make()->components([Button::to('/x', 'Apri')])->render('wonder');
    } finally {
        Theme::set('wonder');
    }

    return str_contains($html, 'Apri') && !str_contains($html, 'text-decoration-none');
});

summary();
