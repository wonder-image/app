<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\IntegrationCard;

return ComponentDoc::for(IntegrationCard::class)
    ->title('IntegrationCard')
    ->group('content')
    ->order(47)
    ->tags('integrazione', 'credenziali', 'stato', 'card', 'modal')
    ->description('Card di un servizio esterno: icona colorata, nome, descrizione, badge di stato in alto a destra, badge accessori (Test, `.env`, nota) e un avviso opzionale. Tutta la card apre il modal indicato (`Button::opensModal()`), che contiene campi, guida e azioni. È la card della pagina Dev → Credenziali.')
    ->uses(Container::class)
    ->docs('servizi/integrazioni.md', 'Integrazioni')
    ->related('status-card', 'modal', 'badge')
    ->example('Base', <<<'PHP'
    IntegrationCard::make('Stripe', 'integration-stripe')
        ->icon('bi-stripe')
        ->variant('primary')
        ->description('Pagamenti con carta tramite Stripe Connect.')
        ->status('Parziale', 'warning')
        ->badge('Test', 'warning', 'bi-cone-striped')
    PHP)
    ->example('In griglia', <<<'PHP'
    (new Container())->columns(2)->gap(3)->components([
        IntegrationCard::make('Server SMTP', 'integration-smtp')
            ->icon('bi-envelope')
            ->description('Invio delle email dal server di posta.')
            ->status('Connessa', 'success')
            ->badge('In uso per l\'invio', 'primary', 'bi-send'),
        IntegrationCard::make('Google Maps', 'integration-maps')
            ->icon('bi-geo-alt')
            ->description('Mappe, recensioni e orari delle sedi.')
            ->status('Da connettere')
            ->notice('Richiede Google Cloud Platform'),
    ])
    PHP, 'Il modal con l\'id indicato va reso a parte, fuori dalla griglia.');
