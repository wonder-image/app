<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Form\Components\Toggle;

return ComponentDoc::for(Toggle::class)
    ->title('Toggle')
    ->group('choice')
    ->order(50)
    ->tags('interruttore', 'switch', 'on', 'off', 'impostazioni')
    ->description('Un interruttore acceso/spento con etichetta e descrizione, per le pagine di configurazione dove ogni riga è una scelta spiegata. `values()` sceglie i due valori postati; un campo nascosto manda sempre quello «spento», così il form trasmette qualcosa anche quando l\'interruttore è staccato. Nelle Resource: `FormField::key(\'nome\')->toggle()`.')
    ->uses(Container::class, FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->related('check-boolean', 'checkbox')
    ->example('Base', <<<'PHP'
    (new Toggle('notifications'))
        ->label('Notifiche email')
        ->description('Ricevi un\'email a ogni nuovo ordine.')
        ->value('true')
    PHP)
    ->example('Più interruttori con valori propri', <<<'PHP'
    (new Container())->gap(2)->components([
        (new Toggle('shop_enabled'))->label('Negozio attivo')->description('Spento, il catalogo resta visibile ma non si può ordinare.')->values('on', 'off')->value('on'),
        (new Toggle('maintenance'))->label('Manutenzione')->description('Mostra la pagina di cortesia ai visitatori.')->values('on', 'off')->value('off'),
        (new Toggle('beta'))->label('Funzioni sperimentali')->disabled(),
    ])
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('visible')->toggle('true', 'false')->label('Visibile')
    PHP);
