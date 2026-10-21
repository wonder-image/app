<?php
/** php tests/Themes/EmptyStateTest.php */
declare(strict_types=1);

require __DIR__ . '/../../vendor/autoload.php';
require __DIR__ . '/../harness.php';

use Wonder\Elements\Components\Button;
use Wonder\Elements\Frontend\EmptyState;
use Wonder\Themes\Resolver;

check('wonder: il markup delle copie dei moduli (icona + paragrafo)', fn () =>
    EmptyState::make('Nessun indirizzo <salvato>.', 'bi bi-geo-alt')->render('wonder')
        === '<div class="wi-empty-state"><i class="wi-empty-state__icon bi bi-geo-alt" aria-hidden="true"></i><p>Nessun indirizzo &lt;salvato&gt;.</p></div>');

check('wonder: la copia del carrello (ruolo, titolo, testo con classi, azione)', function () {
    $html = EmptyState::make('Il carrello è vuoto.', 'bi bi-cart')
        ->title('Niente & nessuno')
        ->textClass('text tx-secondary')
        ->addClass('w-100')
        ->attr('role', 'status')
        ->action(Button::make('Vai al negozio')->href('/shop/'))
        ->render('wonder');

    return str_starts_with($html, '<div class="wi-empty-state w-100" role="status"><i class="wi-empty-state__icon bi bi-cart" aria-hidden="true"></i>'
        .'<h2 class="subtitle">Niente &amp; nessuno</h2><p class="text tx-secondary">Il carrello è vuoto.</p><a ')
        && str_contains($html, 'href="/shop/"')
        && str_ends_with($html, '>Vai al negozio</a></div>');
});

check('wonder: senza icona né titolo resta il solo paragrafo', fn () =>
    EmptyState::make('Vuoto')->render('wonder') === '<div class="wi-empty-state"><p>Vuoto</p></div>');

check('wonder: le classi delle parti si aggiungono a quelle del tema', fn () =>
    EmptyState::make('x', 'bi bi-bag')->iconClass('tx-primary')->title('T')->titleClass('mb-2')->render('wonder')
        === '<div class="wi-empty-state"><i class="wi-empty-state__icon bi bi-bag tx-primary" aria-hidden="true"></i><h2 class="subtitle mb-2">T</h2><p>x</p></div>');

check("un'azione non renderizzabile è rifiutata", function () {
    try {
        EmptyState::make('x')->action(new stdClass());
    } catch (InvalidArgumentException) {
        return true;
    }

    return false;
});

check('bootstrap: nessun renderer, nessun fallback su wonder', function () {
    try {
        Resolver::renderer(EmptyState::class, 'bootstrap');
    } catch (RuntimeException) {
        return true;
    }

    return false;
});

summary();
