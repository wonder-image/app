<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputTel;

return ComponentDoc::for(InputTel::class)
    ->title('Input telefono')
    ->group('text')
    ->order(50)
    ->tags('telefono', 'tel', 'cellulare', 'phone', 'input')
    ->description('Il campo per un numero di telefono: `type="tel"`, `inputmode="tel"` per la tastiera numerica e `data-wi-phone="true"`, con cui la lib formatta il numero mentre si scrive (`checkPhone()`, in entrambi i temi). Nelle Resource si dichiara con `tel()` o con l\'alias `phone()`, da affiancare a `phonePrefix()` per il prefisso internazionale. Il markup è quello dell\'`InputText` di ciascun tema.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: campi di testo')
    ->docs('concetti/form/form-field.md#geo', 'FormField: prefisso telefonico')
    ->related('input-text', 'input-email')
    ->example('Base', <<<'PHP'
    (new InputTel('phone'))
        ->label('Telefono')
    PHP)
    ->example('Con valore', <<<'PHP'
    (new InputTel('phone'))
        ->label('Cellulare')
        ->value('+39 333 1234567')
        ->autocomplete('tel')
    PHP, 'Il valore salvato esce così com\'è; la lib lo riformatta al primo `change`. `autocomplete(\'tel\')` lascia al browser il numero memorizzato.')
    ->example('Obbligatorio con errore', <<<'PHP'
    (new InputTel('phone'))
        ->label('Telefono')
        ->value('12')
        ->required()
        ->error('Il numero è troppo corto.')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('phone')->tel()->label('Telefono')->required()
    PHP, '`tel()` e `phone()` ritornano `Inputs\InputPhone`, che costruisce questo stesso Element.');
