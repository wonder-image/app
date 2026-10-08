<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\DataItem;

return ComponentDoc::for(DataItem::class)
    ->title('DataItem')
    ->group('content')
    ->order(30)
    ->tags('dato', 'etichetta', 'valore', 'scheda')
    ->description('Un dato di una scheda: l\'etichetta piccola sopra e il valore sotto, senza riquadro. Il valore è escapato (`html()` per markup fidato), dove manca compare il segnaposto, e `action()` mette un\'azione accanto all\'etichetta.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md', 'Componenti UI')
    ->related('info-card', 'metric-card')
    ->example('Base', <<<'PHP'
    DataItem::make('Email', 'mario.rossi@example.com')
    PHP)
    ->example('In griglia, con segnaposto e azione', <<<'PHP'
    (new Container())->columns(3)->gap(3)->components([
        DataItem::make('Telefono', '+39 02 1234567'),
        DataItem::make('Partita IVA', null)->placeholder('n/d'),
        DataItem::make('Sito', 'wonderimage.it')->action('<a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer">Apri</a>'),
    ])
    PHP)
    ->example('Valore in HTML', <<<'PHP'
    DataItem::make('Stato', '<span class="badge text-bg-success">Attivo</span>')->html()
    PHP, 'Con `html()` il valore non viene escapato: solo markup fidato.');
