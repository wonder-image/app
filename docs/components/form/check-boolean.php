<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\CheckBoolean;

return ComponentDoc::for(CheckBoolean::class)
    ->title('CheckBoolean')
    ->group('choice')
    ->order(60)
    ->tags('booleano', 'sì', 'no', 'radio', 'tre stati')
    ->description('Una scelta a tre stati: nessun valore, vero o falso, come due radio affiancati. `values()` dice cosa postare nei tre casi, `trueLabel()` e `falseLabel()` le etichette. Nelle Resource: `FormField::key(\'nome\')->checkBoolean()`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#scelta', 'FormField: scelta')
    ->related('toggle', 'checkbox', 'check-group')
    ->example('Base', <<<'PHP'
    (new CheckBoolean('visible'))
        ->label('Visibile nel sito')
        ->value('true')
    PHP)
    ->example('Valori ed etichette propri', <<<'PHP'
    (new CheckBoolean('newsletter'))
        ->label('Newsletter')
        ->values('', 'yes', 'no')
        ->trueLabel('Iscritto')
        ->falseLabel('Non iscritto')
        ->value('no')
    PHP, 'Il primo valore di `values()` è quello di «nessuna scelta».')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('active')->checkBoolean(['', 'true', 'false'], 'Attivo', 'Sospeso')->label('Stato')
    PHP);
