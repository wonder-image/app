<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputDatetime;

return ComponentDoc::for(InputDatetime::class)
    ->title('Data e ora nativa')
    ->group('date')
    ->order(60)
    ->tags('data', 'ora', 'datetime', 'datetime-local', 'textDatetime')
    ->description('Il campo data e ora nativo (`<input type="datetime-local">`): il valore viaggia nel formato `Y-m-d\TH:i` e il selettore è quello del browser. È un `InputText` con un altro `type`, quindi ha gli stessi modificatori e rende con `form-floating` nel backend e `wi-input-container datetime-local` nel frontend.

Nelle Resource si dichiara con `FormField::key(\'nome\')->textDatetime()`, che normalizza il valore, anche scritto all\'italiana, nel formato del controllo.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#date-ora', 'FormField: date e ora')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('date', 'input-time', 'date-time-range')
    ->note('bootstrap', 'Come per `Date`, il controllo nativo mostra sempre il proprio segnaposto e la label del `form-floating` resta alzata anche a campo vuoto.')
    ->note('wonder', 'Il contenitore prende la classe del `type`, `datetime-local`; non c\'è nessun picker della lib, il calendario e l\'orario sono del browser.')
    ->example('Base', <<<'PHP'
    (new InputDatetime('published_at'))
        ->label('Pubblicazione')
    PHP)
    ->example('Con valore e obbligatorio', <<<'PHP'
    (new InputDatetime('starts_at'))
        ->label('Inizio evento')
        ->value('2026-10-15T09:30')
        ->required()
    PHP, '`value()` vuole il formato `Y-m-d\TH:i`; i secondi non si inseriscono.')
    ->example('Con limiti nativi', <<<'PHP'
    (new InputDatetime('appointment'))
        ->label('Appuntamento')
        ->attr('min', '2026-10-08T08:00')
        ->attr('max', '2026-10-31T19:00')
        ->attr('step', 1800)
    PHP, 'Non esistono helper `min()` / `max()`: gli attributi nativi si scrivono con `attr()`. Per coerenza il DSL `textDatetime()` non espone `dateMin()` / `dateMax()`.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('published_at')->textDatetime()->label('Pubblicazione')->required()->value('05/10/2026 14:30')
    PHP, '`textDatetime()` costruisce questo Element e porta `05/10/2026 14:30` a `2026-10-05T14:30`, il formato che il controllo accetta.');
