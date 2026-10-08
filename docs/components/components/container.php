<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\InfoCard;

return ComponentDoc::for(Container::class)
    ->title('Container')
    ->group('layout')
    ->order(20)
    ->tags('griglia', 'colonne', 'wrapper', 'masonry')
    ->description('Un contenitore generico senza cornice: una griglia con `columns()` e `gap()`, oppure un wrapper puro con `noGrid()` quando serve solo un `<div>` con le proprie classi (per esempio un `ratio` Bootstrap attorno a un iframe). `masonry()` impila i figli in colonne che si riempiono dall\'alto.')
    ->uses(Badge::class, Button::class, InfoCard::class)
    ->docs('concetti/componenti/README.md#layout-dei-media', 'Componenti UI')
    ->related('card', 'info-card')
    ->note('wonder', 'Le colonne e lo spazio usano le utility della lib (`col-*`, `gap-*`); nel backend sono `row`, `row-col-*` e `g-*` di Bootstrap.')
    ->example('Base', <<<'PHP'
    (new Container())->columns(3)->gap(3)->components([
        Button::make('Uno'),
        Button::make('Due'),
        Button::make('Tre'),
    ])
    PHP)
    ->example('Wrapper puro con le proprie classi', <<<'PHP'
    (new Container())
        ->noGrid()
        ->addClass('p-3 border rounded')
        ->components([
            Badge::make('Dentro un div con bordo')->variant('primary'),
        ])
    PHP, 'Con `noGrid()` il contenitore non riceve classi di griglia: solo quelle che gli dai.')
    ->example(
        Example::make('Masonry')
            ->code(<<<'PHP'
            (new Container())->masonry(2, '14rem', '1rem')->components([
                InfoCard::make('Locali', 4),
                InfoCard::make('Camere', 2),
                InfoCard::make('Bagni', 1),
                InfoCard::make('Superficie', '120 mq'),
            ])
            PHP)
            ->description('Sotto la larghezza minima data le colonne tornano una sola, senza media query. `InfoCard` è un componente del backend, per questo l\'esempio è solo Bootstrap.')
            ->themes('bootstrap')
    );
