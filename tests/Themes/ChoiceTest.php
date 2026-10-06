<?php
/** php tests/Themes/ChoiceTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Choice;
use Wonder\Elements\Components\ChoiceGroup;

$corriere = static fn (): Choice => Choice::make('shipping_method_id', 3)
    ->title('Rossi & <Figli>')
    ->text('2-3 giorni "lavorativi"')
    ->aside('4,90 €');

foreach (['wonder', 'bootstrap'] as $theme) {
    check("{$theme}: il Choice è un label con l'input radio, nome e valore", function () use ($corriere, $theme) {
        $html = $corriere()->render($theme);

        return str_starts_with($html, '<label ')
            && str_contains($html, 'type="radio"')
            && str_contains($html, 'name="shipping_method_id"')
            && str_contains($html, 'value="3"')
            && str_contains($html, 'data-choice-input')
            && !str_contains($html, ' checked')
            && !str_contains($html, ' disabled');
    });

    check("{$theme}: titolo, testo e aside escono escapati nelle tre parti", function () use ($corriere, $theme) {
        $html = $corriere()->render($theme);

        return str_contains($html, 'data-choice-title>Rossi &amp; &lt;Figli&gt;</span>')
            && str_contains($html, 'data-choice-text>2-3 giorni &quot;lavorativi&quot;</span>')
            && str_contains($html, 'data-choice-aside>4,90 €</span>')
            && !str_contains($html, '<Figli>');
    });

    check("{$theme}: checked, disabled e checkbox arrivano sull'input", function () use ($theme) {
        $html = Choice::make('terms', 'yes')->type('checkbox')->title('Accetto')->checked()->disabled()->render($theme);

        return str_contains($html, 'type="checkbox"')
            && str_contains($html, ' checked')
            && str_contains($html, ' disabled')
            && Choice::make('x', 1)->type('select')->getSchema('type') === 'radio';
    });

    check("{$theme}: le parti vuote ci sono lo stesso, nascoste", function () use ($theme) {
        $html = Choice::make('location_id', 1)->title('Sede')->render($theme);

        return str_contains($html, 'data-choice-text hidden></span>')
            && str_contains($html, 'data-choice-aside hidden></span>')
            && str_contains($html, 'data-choice-title>Sede</span>');
    });

    check("{$theme}: classi e attributi dati vanno sul label", function () use ($corriere, $theme) {
        $html = $corriere()->class('mt-2')->attr('data-x', 'a"b')->render($theme);
        $base = $theme === 'wonder' ? 'wi-choice' : 'card';

        return str_starts_with($html, '<label class="'.$base.' mt-2')
            && str_contains($html, 'data-x="a&quot;b"');
    });

    check("{$theme}: il gruppo è un fieldset con la legend escapata e i Choice nella lista", function () use ($corriere, $theme) {
        $html = ChoiceGroup::make('Metodo <di> spedizione')
            ->choices($corriere(), Choice::make('shipping_method_id', 4)->title('Posta')->checked())
            ->attr('data-checkout-shipping-methods', true)
            ->render($theme);

        return str_starts_with($html, '<fieldset ')
            && str_contains($html, 'data-checkout-shipping-methods')
            && str_contains($html, 'Metodo &lt;di&gt; spedizione</legend>')
            && str_contains($html, 'data-choice-list>')
            && substr_count($html, '<label ') === 2
            && substr_count($html, ' checked') === 1
            && str_ends_with($html, '</div></fieldset>');
    });

    check("{$theme}: senza legend non c'è una legend vuota", fn () =>
        !str_contains(ChoiceGroup::make()->choices(Choice::make('a', 1)->title('A'))->render($theme), '<legend')
    );

    check("{$theme}: id() arriva sul fieldset e sul label, per le ancore come #consegna", fn () =>
        str_starts_with(ChoiceGroup::make('L')->id('consegna')->render($theme), '<fieldset ')
        && str_contains(ChoiceGroup::make('L')->id('consegna')->render($theme), 'id="consegna"')
        && str_contains(Choice::make('a', 1)->id('scelta-a')->render($theme), 'id="scelta-a"')
    );
}

check('wonder: le classi della lib', function () use ($corriere) {
    $choice = $corriere()->render('wonder');
    $group = ChoiceGroup::make('Metodo')->choices($corriere())->render('wonder');

    return str_contains($choice, '<span class="wi-choice__body">')
        && str_contains($choice, 'class="wi-choice__title"')
        && str_contains($choice, 'class="wi-choice__text"')
        && str_contains($choice, 'class="wi-choice__aside"')
        && str_contains($group, 'class="wi-choice-group"')
        && str_contains($group, '<legend class="wi-choice-group__legend">')
        && str_contains($group, 'class="wi-choice-group__list"');
});

check('bootstrap: form-check dentro una card', function () use ($corriere) {
    $html = $corriere()->render('bootstrap');

    return str_contains($html, '<span class="card-body d-flex align-items-start gap-3">')
        && str_contains($html, '<span class="form-check m-0">')
        && str_contains($html, 'class="form-check-input"');
});

summary();
