<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Frontend\EmptyState;

return ComponentDoc::for(EmptyState::class)
    ->title('EmptyState')
    ->group('feedback')
    ->order(50)
    ->tags('vuoto', 'nessun risultato', 'frontend', 'account')
    ->description('Lo stato vuoto di una lista del frontend: icona, titolo, testo e un\'azione opzionale, in quest\'ordine. Titolo e testo si escapano; l\'azione è un componente renderizzabile, di solito un `Button`. `iconClass()`, `titleClass()` e `textClass()` aggiungono classi alle parti, `titleTag()` sceglie il livello del titolo; `attr()` e `addClass()` vanno sul contenitore.')
    ->uses(Button::class)
    ->docs('concetti/componenti/README.md#emptystate-e-pagination', 'Componenti UI')
    ->related('pagination', 'alert')
    ->note('wonder', 'Usa `.wi-empty-state` e `.wi-empty-state__icon` della lib; il titolo è un `<h2 class="subtitle">`, `titleTag(\'h3\')` ne cambia il livello (da `h2` a `h6`) dentro una sezione che ha già un `h2`.')
    ->example('Base', <<<'PHP'
    EmptyState::make('Non hai ancora salvato un indirizzo.', 'bi bi-geo-alt')
    PHP)
    ->example('Con titolo e azione', <<<'PHP'
    EmptyState::make('Aggiungi qualche prodotto per iniziare.', 'bi bi-bag')
        ->title('Il carrello è vuoto')
        ->textClass('text tx-secondary')
        ->attr('role', 'status')
        ->action(Button::make('Vai al negozio')->href('/shop/')->variant('black'))
    PHP, 'È il markup del carrello: `role="status"` lo annuncia alle tecnologie assistive.');
