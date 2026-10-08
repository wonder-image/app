<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputPercentige;

return ComponentDoc::for(InputPercentige::class)
    ->title('Input percentuale')
    ->group('text')
    ->order(80)
    ->tags('percentuale', 'percentige', 'sconto', 'iva', 'input')
    ->description('La variante percentuale dell\'`InputNumber`: emette `data-wi-percentige="true"` e la lib aggiunge il `%` in coda (preset `AUTONUMERIC_PERCENTIGE`), con la virgola decimale e senza separatore delle migliaia. Il nome, con la grafia `percentige`, è quello storico del framework e torna nel DSL come `percentige()`. Il valore inviato è il numero puro: `12.5`, non «12,5%».')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#formatting-numerico', 'FormField: formatting numerico')
    ->related('input-number', 'input-price')
    ->example('Base', <<<'PHP'
    (new InputPercentige('discount'))
        ->label('Sconto')
        ->value('12.5')
    PHP)
    ->example('Intero', <<<'PHP'
    (new InputPercentige('vat'))
        ->label('IVA')
        ->value('22')
        ->decimal(0)
    PHP, '`decimal(0)` toglie i decimali: la percentuale resta «22%».')
    ->example('Con errore', <<<'PHP'
    (new InputPercentige('discount'))
        ->label('Sconto')
        ->value('140')
        ->error('Lo sconto non può superare il 100%.')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('discount')->percentige()->label('Sconto')->decimal(1)
    PHP, '`percentige()` ritorna `Inputs\InputPercentige`, con gli stessi setter di formato di `number()`.');
