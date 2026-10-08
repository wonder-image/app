<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Choice;
use Wonder\Elements\Components\ChoiceGroup;

return ComponentDoc::for(ChoiceGroup::class)
    ->title('ChoiceGroup')
    ->group('choice')
    ->order(20)
    ->tags('fieldset', 'radio', 'scelte', 'segmented', 'lista')
    ->description('Un `fieldset` con la `legend` e una lista di `Choice`. `variant(\'segmented\')` mette le scelte in riga, unite; `variant(\'list\')` le impila con i bordi in comune. Il contenitore delle scelte porta `data-choice-list`, così il JS sostituisce le voci senza toccare la legend.')
    ->uses(Choice::class)
    ->docs('concetti/componenti/README.md#choice-choicegroup-e-steps', 'Componenti UI')
    ->related('choice', 'steps')
    ->note('bootstrap', 'Le varianti diventano `btn-group` e `list-group`.')
    ->example('Base', <<<'PHP'
    ChoiceGroup::make('Metodo di spedizione')
        ->choices(
            Choice::make('shipping_method_id', 1)->title('Corriere')->text('2-3 giorni')->aside('4,90 €')->checked(),
            Choice::make('shipping_method_id', 2)->title('Posta')->text('5-7 giorni')->aside('2,50 €'),
            Choice::make('shipping_method_id', 3)->title('Ritiro in negozio')->aside('Gratis'),
        )
    PHP)
    ->example('Segmentato', <<<'PHP'
    ChoiceGroup::make('Come vuoi riceverlo?')
        ->variant('segmented')
        ->choices(
            Choice::make('delivery', 'ship')->title('Spedisci')->checked(),
            Choice::make('delivery', 'pickup')->title('Ritiro'),
        )
    PHP)
    ->example('Lista unita', <<<'PHP'
    ChoiceGroup::make('Pagamento')
        ->variant('list')
        ->choices(
            Choice::make('payment_method_id', 1)->title('Carta')->panel('Verrai reindirizzato al circuito.')->checked(),
            Choice::make('payment_method_id', 2)->title('Bonifico')->panel('Le coordinate arrivano via email.'),
            Choice::make('payment_method_id', 3)->title('Contrassegno')->aside('+ 3,00 €'),
        )
    PHP);
