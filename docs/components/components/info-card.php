<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\InfoCard;

return ComponentDoc::for(InfoCard::class)
    ->title('InfoCard')
    ->group('content')
    ->order(40)
    ->tags('etichetta', 'valore', 'card', 'scheda')
    ->description('Una card con un\'etichetta e un valore descrittivo; sostituisce il vecchio `prettyInfo()`. Solo `null` e la stringa vuota mostrano il segnaposto: `0` resta un valore. `valueLevel()` sceglie il livello tipografico del valore.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md#infocard-e-metriccard', 'Componenti UI')
    ->related('metric-card', 'data-item', 'card')
    ->example('Base', <<<'PHP'
    InfoCard::make('Locali', 4)
    PHP)
    ->example('In griglia', <<<'PHP'
    (new Container())->columns(3)->gap(3)->components([
        InfoCard::make('Locali', 4),
        InfoCard::make('Camere da letto', 2),
        InfoCard::make('Bagni', 0),
    ])
    PHP, 'Dentro `columns(3)` ogni card prende una colonna; `0` è un valore valido.')
    ->example('Segnaposto e livello', <<<'PHP'
    (new Container())->columns(2)->gap(3)->components([
        InfoCard::make('Giardino', null)->placeholder('n/d'),
        InfoCard::make('Classe energetica', 'A4')->valueLevel(3),
    ])
    PHP);
