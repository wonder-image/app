<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Form\Components\InputNumber;

return ComponentDoc::for(InputNumber::class)
    ->title('Input numero')
    ->group('text')
    ->order(60)
    ->tags('numero', 'number', 'decimali', 'quantità', 'autonumeric', 'input')
    ->description('Il campo numerico: un `<input type="text">` con `data-wi-number="true"`, che la lib affida ad AutoNumeric in entrambi i temi: il valore si vede formattato e al submit parte il numero puro (`1299.5`). Di default usa il formato italiano, virgola decimale e nessun separatore delle migliaia; `decimal()`, `decimalSeparator()`, `groupSeparator()`, `symbol()` e `symbolPlacement(\'p\'|\'s\')` lo cambiano scrivendo gli attributi `data-wi-number-*`, mentre `decimals()` passa il valore allo schema senza toccare il formato. Per prezzi e percentuali ci sono le varianti `InputPrice` e `InputPercentige`.')
    ->uses(Container::class, FormField::class)
    ->docs('concetti/form/form-field.md#formatting-numerico', 'FormField: formatting numerico')
    ->docs('concetti/risorse/database.md', 'Model e Database: colonne DECIMAL')
    ->related('input-price', 'input-percentige', 'input-text')
    ->example('Base', <<<'PHP'
    (new InputNumber('quantity'))
        ->label('Quantità')
        ->value('12')
    PHP, 'Senza impostazioni il numero esce con la virgola decimale e senza punto delle migliaia: «20000,5», non «20.000,5».')
    ->example('Decimali e unità di misura', <<<'PHP'
    (new InputNumber('weight'))
        ->label('Peso')
        ->value('2.5')
        ->decimal(2)
        ->symbol(' kg')
        ->symbolPlacement('s')
    PHP, '`decimal(2)` mostra due cifre decimali; `symbol()` con `symbolPlacement(\'s\')` aggiunge l\'unità in coda, `\'p\'` la mette davanti. Un altro valore di `symbolPlacement()` solleva un\'eccezione.')
    ->example('Intero con separatore delle migliaia', <<<'PHP'
    (new InputNumber('population'))
        ->label('Abitanti')
        ->value('1250000')
        ->decimal(0)
        ->groupSeparator('.')
    PHP, '`decimal(0)` toglie i decimali e `groupSeparator(\'.\')` raggruppa le migliaia all\'italiana.')
    ->example('Più campi con formati diversi', <<<'PHP'
    (new Container())->gap(3)->components([
        (new InputNumber('width'))->label('Larghezza')->value('120')->symbol(' cm')->symbolPlacement('s')->decimal(0),
        (new InputNumber('ratio'))->label('Rapporto')->value('1.618')->decimal(3),
    ])
    PHP, 'Ogni campo porta il proprio formato negli attributi `data-wi-number-*`, letti dalla lib all\'inizializzazione.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('weight')->number()->label('Peso')->decimal(2)->suffix(' kg')
    PHP, '`number()` ritorna `Inputs\InputNumber`, con gli stessi setter dell\'Element più `integer()` (come `decimal(0)`) e `suffix()` (come `symbol()` più `symbolPlacement(\'s\')`).');
