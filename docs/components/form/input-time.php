<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputTime;
use Wonder\Elements\Form\Form;

return ComponentDoc::for(InputTime::class)
    ->title('Orario')
    ->group('date')
    ->order(70)
    ->tags('ora', 'orario', 'time', 'minuti', 'timeInput')
    ->description('Il campo orario nativo (`<input type="time">`), con `step($secondi)` per la granularità dei minuti (`900` = 15 minuti). È un `InputText` con un altro `type`: stessi modificatori, `form-floating` nel backend e `wi-input-container time` nel frontend.

Nelle Resource si dichiara con `FormField::key(\'nome\')->timeInput($step)`, che applica lo step solo se maggiore di zero.')
    ->uses(Form::class, FormField::class)
    ->docs('concetti/form/form-field.md#date-ora', 'FormField: date e ora')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('input-datetime', 'date')
    ->note('bootstrap', 'Il controllo nativo mostra sempre il proprio segnaposto `--:--` e la label del `form-floating` resta alzata anche a campo vuoto.')
    ->note('wonder', 'Il contenitore prende la classe `time`; l\'orologio è quello del browser, la lib non aggiunge nessun picker.')
    ->example('Base', <<<'PHP'
    (new InputTime('open_time'))
        ->label('Apertura')
    PHP)
    ->example('Con passo di 15 minuti', <<<'PHP'
    (new InputTime('appointment_time'))
        ->label('Ora appuntamento')
        ->value('09:30')
        ->step(900)
        ->required()
    PHP, '`step()` vuole i secondi: `900` propone solo i quarti d\'ora; `value()` è `HH:mm`.')
    ->example('Apertura e chiusura', <<<'PHP'
    (new Form())->columns(2)->gap(3)->components([
        (new InputTime('open_time'))->label('Apre')->value('09:00')->step(1800),
        (new InputTime('close_time'))->label('Chiude')->value('19:00')->step(1800),
    ])
    PHP, 'Due orari affiancati in un `Form` a due colonne, come in una riga di orari di un negozio.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('open_time')->timeInput(900)->label('Apre')->value('09:00')
    PHP, '`timeInput($step)` costruisce questo Element e chiama `step()` solo quando il passo è maggiore di zero.');
