<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\StatusCard;

return ComponentDoc::for(StatusCard::class)
    ->title('StatusCard')
    ->group('content')
    ->order(46)
    ->tags('stato', 'kpi', 'icona', 'card', 'home')
    ->description('Card di stato con icona colorata, titolo, valore e una riga di dettaglio. Con `href()` tutta la card diventa un link (`stretched-link`). `variant()` colora icona e sfondo con le classi `-subtle` / `-emphasis` di Bootstrap. È la card della fascia di stato nella home del backend.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md#statuscard-linkcard-e-linklistcard', 'Componenti UI')
    ->related('link-card', 'link-list-card', 'metric-card')
    ->example('Base', <<<'PHP'
    StatusCard::make('Scheduler', 'Attivo')
        ->icon('bi-clock-history')
        ->variant('success')
        ->detail('Nessun errore nelle ultime 24 ore')
        ->href('#')
    PHP)
    ->example('In griglia', <<<'PHP'
    (new Container())->columns(2)->gap(3)->components([
        StatusCard::make('Errori', 3)->icon('bi-bug')->variant('danger')->detail('Errori da controllare'),
        StatusCard::make('Ordini', 12)->icon('bi-bag')->detail('Da spedire oggi'),
    ])
    PHP, 'Senza `href()` la card non è cliccabile.');
