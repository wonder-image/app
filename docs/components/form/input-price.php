<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputPrice;

return ComponentDoc::for(InputPrice::class)
    ->title('Input prezzo')
    ->group('text')
    ->order(70)
    ->tags('prezzo', 'price', 'valuta', 'euro', 'importo', 'input')
    ->description('La variante prezzo dell\'`InputNumber`: emette `data-wi-price="true"` al posto di `data-wi-number` e parte già nel formato italiano «1.299,90 €», con virgola decimale, punto delle migliaia e ` €` in coda. Una configurazione esplicita vince sempre, anche vuota: `groupSeparator(\'\')` toglie il punto delle migliaia e `symbol(\'\')` toglie l\'euro. Al submit AutoNumeric manda il numero puro, quindi il PHP riceve `1299.9` e non deve interpretare il testo formattato.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#formatting-numerico', 'FormField: formatting numerico')
    ->related('input-number', 'input-percentige')
    ->example('Base', <<<'PHP'
    (new InputPrice('price'))
        ->label('Prezzo')
        ->value('1299.9')
    PHP, 'Il valore si passa come numero puro: è la lib a mostrarlo come «1.299,90 €».')
    ->example('Altra valuta', <<<'PHP'
    (new InputPrice('price'))
        ->label('Prezzo in dollari')
        ->value('1299.9')
        ->symbol('$')
        ->symbolPlacement('p')
        ->decimalSeparator('.')
        ->groupSeparator(',')
    PHP, 'Gli stessi setter dell\'`InputNumber`: simbolo davanti con `symbolPlacement(\'p\')` e separatori anglosassoni.')
    ->example('Senza simbolo né migliaia', <<<'PHP'
    (new InputPrice('cost'))
        ->label('Costo unitario')
        ->value('1299.9')
        ->symbol('')
        ->groupSeparator('')
    PHP, 'La stringa vuota è una scelta e sovrascrive il default: resta solo «1299,90».')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('price')->price()->label('Prezzo')->value('49.9')->required()
    PHP, '`price()` ritorna `Inputs\InputPrice`: stessi setter di `number()`, ma non usare `suffix()`, che prenderebbe il posto dell\'euro.');
