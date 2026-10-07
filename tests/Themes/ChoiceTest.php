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
        $base = $theme === 'wonder' ? 'wi-choice' : 'card user-select-none';

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
    check("{$theme}: icon() stampa l'icona di Bootstrap Icons, ripulita", function () use ($theme) {
        $html = Choice::make('fulfillment_type', 'pickup')->icon('bi-shop')->title('Ritiro')->render($theme);
        $dirty = Choice::make('a', 1)->icon('Shop"><script>')->render($theme);

        return str_contains($html, 'bi bi-shop"')
            && str_contains($html, 'aria-hidden="true"')
            && str_contains($dirty, 'bi bi-shopscript"')
            && !str_contains($dirty, '<script>')
            && !str_contains(Choice::make('a', 1)->title('A')->render($theme), 'bi bi-');
    });

    check("{$theme}: icons() mostra al massimo tre loghi e poi +N", function () use ($theme) {
        $logo = static fn (string $name): array => ['src' => "/icons/{$name}.svg", 'alt' => ucfirst($name)];
        $html = Choice::make('payment_method_id', 1)->title('Carta')
            ->icons([$logo('visa'), $logo('master'), ['alt' => 'senza src'], $logo('maestro'), $logo('amex'), $logo('gpay')])
            ->render($theme);

        return substr_count($html, '<img ') === 3
            && str_contains($html, 'src="/icons/visa.svg"')
            && str_contains($html, 'alt="Visa"')
            && !str_contains($html, 'amex.svg')
            && str_contains($html, '>+2</span>')
            && str_contains($html, 'data-choice-icons>');
    });

    check("{$theme}: icons() escapa src e alt e rispetta un massimo diverso", function () use ($theme) {
        $html = Choice::make('p', 1)->icons([['src' => '/a"b.svg', 'alt' => '<x>'], ['src' => '/c.svg']], 1)->render($theme);

        return str_contains($html, 'src="/a&quot;b.svg"')
            && str_contains($html, 'alt="&lt;x&gt;"')
            && substr_count($html, '<img ') === 1
            && str_contains($html, '>+1</span>')
            && Choice::make('p', 1)->icons([], 0)->getSchema('icons_max') === 1;
    });

    check("{$theme}: senza loghi né pannello le parti ci sono, nascoste", function () use ($theme) {
        $html = Choice::make('p', 1)->title('Bonifico')->render($theme);

        return str_contains($html, 'data-choice-icons hidden></span>')
            && str_contains($html, 'data-choice-panel hidden></span>');
    });

    check("{$theme}: panel() esce escapato, dopo l'aside e prima della chiusura", function () use ($theme) {
        $html = Choice::make('p', 1)->title('PayPal')->aside('1,00 €')
            ->icons([['src' => '/p.svg', 'alt' => 'PayPal']])
            ->panel('Verrai <reindirizzato> a "PayPal"')
            ->render($theme);
        $panel = strpos($html, 'data-choice-panel');

        return str_contains($html, 'data-choice-panel>Verrai &lt;reindirizzato&gt; a &quot;PayPal&quot;</span>')
            && strpos($html, 'data-choice-icons') < strpos($html, 'data-choice-aside')
            && strpos($html, 'data-choice-aside') < $panel
            && str_ends_with($html, '</span></label>');
    });

    check("{$theme}: variant() accetta solo segmented e list", fn () =>
        ChoiceGroup::make()->variant(' LIST ')->getSchema('variant') === 'list'
        && ChoiceGroup::make()->variant('segmented')->getSchema('variant') === 'segmented'
        && ChoiceGroup::make()->variant('boh')->getSchema('variant') === ''
        && ChoiceGroup::make()->getSchema('variant') === ''
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

check('wonder: varianti, icona, loghi e pannello con le classi della lib', function () {
    $choice = Choice::make('p', 1)->icon('truck')->title('Spedisci')
        ->icons([['src' => '/a.svg'], ['src' => '/b.svg']], 1)->panel('Istruzioni')->render('wonder');
    $segmented = ChoiceGroup::make()->variant('segmented')->choices(Choice::make('a', 1))->render('wonder');
    $list = ChoiceGroup::make()->variant('list')->choices(Choice::make('a', 1))->render('wonder');
    $plain = ChoiceGroup::make()->choices(Choice::make('a', 1))->render('wonder');

    return str_contains($choice, '<i class="wi-choice__icon bi bi-truck" aria-hidden="true"></i><span class="wi-choice__body">')
        && str_contains($choice, '<span class="wi-choice__icons" data-choice-icons>')
        && str_contains($choice, '<span class="wi-choice__more">+1</span>')
        && str_contains($choice, '<span class="wi-choice__panel" data-choice-panel>Istruzioni</span></label>')
        && str_contains($choice, 'width="38" height="24"')
        && str_contains($segmented, 'class="wi-choice-group wi-choice-group--segmented"')
        && str_contains($list, 'class="wi-choice-group wi-choice-group--list"')
        && !str_contains($plain, 'wi-choice-group--');
});

check('bootstrap: form-check dentro una card', function () use ($corriere) {
    $html = $corriere()->render('bootstrap');

    return str_contains($html, '<span class="card-body d-flex align-items-start gap-3">')
        && str_starts_with($html, '<label class="card user-select-none"')
        && str_contains($html, '<span class="form-check m-0">')
        && str_contains($html, 'class="form-check-input"');
});

check('bootstrap: btn-group per i segmenti, list-group per la lista, card-footer per il pannello', function () {
    $group = static fn (string $variant): string => ChoiceGroup::make()->variant($variant)->choices(Choice::make('a', 1))->render('bootstrap');
    $choice = Choice::make('p', 1)->title('Bonifico')->panel('IBAN')->render('bootstrap');

    return str_contains($group('segmented'), '<div class="btn-group w-100" data-choice-list>')
        && str_contains($group('list'), '<div class="list-group" data-choice-list>')
        && str_contains($group(''), '<div class="d-grid gap-2" data-choice-list>')
        && str_contains($choice, '</span><span class="card-footer small" style="display:block;-webkit-user-select:text;user-select:text" data-choice-panel>IBAN</span></label>')
        && str_contains(Choice::make('p', 1)->render('bootstrap'), '<span class="card-footer small" style="display:block;-webkit-user-select:text;user-select:text" data-choice-panel hidden></span>')
        && str_contains(Choice::make('p', 1)->render('bootstrap'), '<span class="align-items-center gap-1" style="display:flex" data-choice-icons hidden></span>');
});

summary();
