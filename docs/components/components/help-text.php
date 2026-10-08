<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\HelpText;

return ComponentDoc::for(HelpText::class)
    ->title('HelpText')
    ->group('feedback')
    ->order(40)
    ->tags('aiuto', 'nota', 'didascalia')
    ->description('Una riga di testo di aiuto, piccola e attenuata, da mettere sotto un campo o un riquadro per spiegare cosa ci si aspetta.')
    ->docs('concetti/componenti/README.md', 'Componenti UI')
    ->related('tooltip', 'text')
    ->example('Base', <<<'PHP'
    HelpText::make('Il codice fiscale viene verificato al salvataggio.')
    PHP)
    ->example('Dentro un layout', <<<'PHP'
    HelpText::make('Lascia vuoto per usare il valore del sito.')->columnSpan(12)
    PHP, 'Nel layout di una Resource occupa la colonna che gli dai con `columnSpan()`.');
