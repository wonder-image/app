<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\LinkCard;

return ComponentDoc::for(LinkCard::class)
    ->title('LinkCard')
    ->group('content')
    ->order(47)
    ->tags('link', 'scorciatoia', 'card', 'home')
    ->description('Card cliccabile con icona, titolo e una descrizione facoltativa: una scorciatoia verso una pagina. Il titolo è il link e copre tutta la card (`stretched-link`).')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md#statuscard-linkcard-e-linklistcard', 'Componenti UI')
    ->related('status-card', 'link-list-card', 'card')
    ->example('Base', <<<'PHP'
    LinkCard::make('Richieste', '#')->icon('bi-envelope')
    PHP)
    ->example('Con descrizione e variante', <<<'PHP'
    (new Container())->columns(2)->gap(3)->components([
        LinkCard::make('Ordini', '#')->icon('bi-bag')->description('Ordini da evadere'),
        LinkCard::make('Errori', '#')->icon('bi-bug')->variant('danger'),
    ])
    PHP);
