<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\SelectOld;

return ComponentDoc::for(SelectOld::class)
    ->title('Select (legacy)')
    ->group('choice')
    ->order(20)
    ->tags('select', 'legacy', 'nativo', 'frontend')
    ->deprecated('Variante storica del frontend, mantenuta per i form che non caricano lo script di stile della lib e per l\'helper legacy `selectOld()` di `app/function/frontend/input.php`: in codice nuovo usa `Select`.')
    ->description('Il `<select>` nativo del frontend. Estende `Select` con la stessa API (`options()`, `placeholder()`, `value()`) ma il suo renderer non scrive `data-wi-select` sul contenitore: la lib non lo veste e il browser mostra la tendina di sistema. Esiste solo nel tema Wonder; nel backend il `Select` normale copre già il caso.')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('select')
    ->note('wonder', 'Niente `data-wi-select` e niente campo nascosto per il `multiple`: è il select che il browser disegna da solo.')
    ->example('Base', <<<'PHP'
    (new SelectOld('language'))
        ->label('Lingua')
        ->options(['it' => 'Italiano', 'en' => 'Inglese', 'de' => 'Tedesco'])
        ->value('it')
    PHP)
    ->example('Con voce vuota e obbligatorio', <<<'PHP'
    (new SelectOld('province'))
        ->label('Provincia')
        ->options(['MI' => 'Milano', 'BG' => 'Bergamo', 'BS' => 'Brescia'])
        ->placeholder('Scegli la provincia')
        ->required()
    PHP, 'Senza un `value()` nessuna voce esce `selected`: la prima mostrata è la voce vuota di `placeholder()`.');
