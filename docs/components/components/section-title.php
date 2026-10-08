<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\SectionTitle;

return ComponentDoc::for(SectionTitle::class)
    ->title('SectionTitle')
    ->group('layout')
    ->order(50)
    ->tags('titolo', 'intestazione', 'sezione', 'h5')
    ->description('Il titolo di una sezione dentro un riquadro o un layout: un heading con il livello scelto e un tooltip opzionale accanto.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md', 'Componenti UI')
    ->related('card', 'tooltip', 'text')
    ->example('Base', <<<'PHP'
    SectionTitle::make('Dati di fatturazione')
    PHP, 'Il livello di default è `h6`.')
    ->example('Livelli e tooltip', <<<'PHP'
    (new Container())->gap(2)->components([
        SectionTitle::make('Titolo di livello 4')->level(4),
        SectionTitle::make('Titolo di livello 5')->level(5),
        SectionTitle::make('Con un aiuto')->level(6)->tooltip('Compare al passaggio del mouse.'),
    ])
    PHP);
