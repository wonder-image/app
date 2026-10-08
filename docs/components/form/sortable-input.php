<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\SortableInput;

return ComponentDoc::for(SortableInput::class)
    ->title('SortableInput')
    ->group('advanced')
    ->order(60)
    ->tags('righe', 'ordinabile', 'legacy', 'tabella')
    ->description('La lista riordinabile di righe con celle input del backend storico: bottoni su/giù/elimina per riga, un template clonato dal «+» e le funzioni JS già caricate dall\'admin (`rowOrder`, `copyRow`). L\'identità delle righe passa da `id[]` e `position[]`.')
    ->deprecated('Resta solo per compatibilità: per le righe ripetibili usa `Repeater` (`FormField::repeater()`), che porta con sé il proprio JavaScript e usa `data-wi-row-key`.')
    ->docs('concetti/form/repeater.md', 'Repeater')
    ->related('repeater')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new SortableInput('variants'))
                ->label('Varianti')
                ->title('Varianti del prodotto')
                ->columns([
                    ['name' => 'name', 'label' => 'Nome', 'type' => 'text'],
                    ['name' => 'price', 'label' => 'Prezzo', 'type' => 'price'],
                    ['name' => 'stock', 'label' => 'Scorta', 'type' => 'number'],
                ])
                ->rows([
                    ['id' => 1, 'name' => 'Rossa', 'price' => '12.00', 'stock' => 4],
                    ['id' => 2, 'name' => 'Blu', 'price' => '12.50', 'stock' => 0],
                ])
            PHP)
            ->height(260)
    );
