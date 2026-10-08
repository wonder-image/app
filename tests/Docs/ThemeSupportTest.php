<?php
/**
 * ThemeSupport legge la disponibilità dal Resolver, non da una lista.
 *
 *   php tests/Docs/ThemeSupportTest.php
 */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\ThemeSupport;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Form\Components\Repeater;

echo "ThemeSupport\n";

check('i temi sono quelli del Registry', function () {
    return ThemeSupport::themes() === ['wonder', 'bootstrap'];
});

check('un componente con renderer in entrambi i temi è disponibile in entrambi, con il renderer speculare', function () {
    $support = ThemeSupport::for(ComponentDoc::for(Button::class));

    return $support['wonder']->available && $support['bootstrap']->available
        && $support['wonder']->renderer === \Wonder\Themes\Wonder\Components\Button::class
        && !$support['wonder']->inherited && !$support['bootstrap']->inherited;
});

check('un componente senza renderer in un tema non è disponibile lì, con il motivo', function () {
    $support = ThemeSupport::for(ComponentDoc::for(Repeater::class));

    return !$support['wonder']->available
        && $support['wonder']->renderer === null
        && str_contains($support['wonder']->reason, 'Wonder')
        && $support['bootstrap']->available;
});

check('Card esiste solo in Bootstrap', function () {
    $support = ThemeSupport::for(ComponentDoc::for(Card::class));

    return !$support['wonder']->available && $support['bootstrap']->available;
});

check('unsupported() forza un tema come non disponibile con il motivo dato', function () {
    $doc = ComponentDoc::for(Button::class)->unsupported('wonder', 'Solo per la prova.');
    $support = ThemeSupport::for($doc);

    return !$support['wonder']->available && $support['wonder']->reason === 'Solo per la prova.'
        && $support['bootstrap']->available;
});

check('expectedRenderer() rispecchia il namespace dell\'Element nel tema', function () {
    return ThemeSupport::expectedRenderer(Button::class, 'bootstrap') === \Wonder\Themes\Bootstrap\Components\Button::class
        && ThemeSupport::expectedRenderer('Foo\\Bar', 'bootstrap') === null;
});

check('label() scrive il tema con l\'iniziale maiuscola', function () {
    return ThemeSupport::label('wonder') === 'Wonder' && ThemeSupport::label('BOOTSTRAP') === 'Bootstrap';
});

summary();
