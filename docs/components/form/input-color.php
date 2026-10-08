<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputColor;

return ComponentDoc::for(InputColor::class)
    ->title('Input colore')
    ->group('text')
    ->order(90)
    ->tags('colore', 'color', 'esadecimale', 'tema', 'input')
    ->description('Un campo di testo per un colore esadecimale (`#0d6efd`), con `data-wi-check-color="true"`. Nel backend ha la label sopra e un `input-group` con un pallino (`wi-show-color`) del colore scritto, che la lib aggiorna mentre si digita; nel frontend rende come un `InputText` semplice, perché la lib frontend non legge l\'attributo. Chi salva il valore lo controlla con `OptionVisual::color()`, che accetta solo la forma esadecimale.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: campi di testo')
    ->related('input-text', 'input-icon')
    ->note('bootstrap', 'Niente `form-floating`: la label è un `h6 form-label` sopra l\'`input-group`, il placeholder ripete la label e il pallino prende `style="color: <valore>"`.')
    ->note('wonder', 'Rende con il renderer dell\'`InputText`, senza anteprima del colore: `data-wi-check-color` resta nel markup ma la lib frontend non lo usa.')
    ->example('Base', <<<'PHP'
    (new InputColor('color'))
        ->label('Colore principale')
        ->value('#0d6efd')
    PHP)
    ->example('Senza valore', <<<'PHP'
    (new InputColor('color_secondary'))
        ->label('Colore secondario')
    PHP, 'Il pallino del backend resta del colore del testo finché non si scrive un valore.')
    ->example('Con errore', <<<'PHP'
    (new InputColor('color'))
        ->label('Colore principale')
        ->value('blu')
        ->error('Usa un colore esadecimale, per esempio #0d6efd.')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('color')->color()->label('Colore principale')->value('#198754')
    PHP, '`color()` ritorna `Inputs\InputColor`, con i soli modificatori universali.');
