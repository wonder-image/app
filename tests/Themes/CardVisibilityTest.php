<?php
/** php tests/Themes/CardVisibilityTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Card;

/**
 * Un riquadro che si nasconde da sé.
 *
 * Le stesse regole di visibilità condizionale degli input, applicate a un
 * blocco intero: un interruttore deve poter aprire e chiudere tutto quello
 * che governa.
 */
$render = static fn (Card $card): string => $card->render('bootstrap');

check('senza regole il riquadro non porta attributi in più', function () use ($render) {
    $html = $render((new Card)->components([])->columns(12)->columnSpan(12));

    return !str_contains($html, 'data-visible-when')
        && !str_contains($html, 'data-wi-conditional-container');
});

check('visibleWhen scrive campo, valori e contenitore', function () use ($render) {
    $html = $render((new Card)->components([])->columns(12)->columnSpan(12)
        ->visibleWhen('has_variants', 'true'));

    return str_contains($html, 'data-visible-when="has_variants"')
        && str_contains($html, 'data-visible-when-values="true"')
        && str_contains($html, 'data-wi-conditional-container="true"');
});

check('più valori si scrivono separati da virgola', function () use ($render) {
    return str_contains(
        $render((new Card)->components([])->columns(12)->columnSpan(12)
            ->visibleWhen('tipo', ['semplice', 'bundle'])),
        'data-visible-when-values="semplice,bundle"'
    );
});

check('hiddenWhen usa le sue chiavi', function () use ($render) {
    $html = $render((new Card)->components([])->columns(12)->columnSpan(12)
        ->hiddenWhen('has_variants', 'false'));

    return str_contains($html, 'data-hidden-when="has_variants"')
        && str_contains($html, 'data-hidden-when-values="false"');
});

check('un campo vuoto non scrive niente', function () use ($render) {
    return !str_contains(
        $render((new Card)->components([])->columns(12)->columnSpan(12)->visibleWhen('  ', 'true')),
        'data-visible-when'
    );
});

check('gli attributi stanno sul div esterno, prima della card', function () use ($render) {
    $html = $render((new Card)->components([])->columns(12)->columnSpan(12)
        ->visibleWhen('has_variants', 'true'));

    return strpos($html, 'data-visible-when') < strpos($html, 'class="card border"');
});

summary();
