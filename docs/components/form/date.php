<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\Date;
use Wonder\Elements\Form\Form;

return ComponentDoc::for(Date::class)
    ->title('Data nativa')
    ->group('date')
    ->order(10)
    ->tags('data', 'date', 'calendario', 'nativo', 'textDate')
    ->description('Il campo data nativo del browser (`<input type="date">`): il valore viaggia nel formato ISO `Y-m-d` e `min()` / `max()` scrivono gli attributi nativi omonimi, che il browser applica da solo. Il calendario è quello del sistema, senza JavaScript; per il calendario della lib, uguale nei due temi, usa `DatePicker`.

In entrambi i temi rende come un `InputText` con `type="date"`: `form-floating` nel backend, `wi-input-container date` nel frontend. Nelle Resource si dichiara con `FormField::key(\'nome\')->textDate()`, che normalizza il valore a `Y-m-d`.')
    ->uses(Form::class, FormField::class)
    ->docs('concetti/form/form-field.md#date-ora', 'FormField: date e ora')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('date-picker', 'input-datetime', 'input-time')
    ->note('bootstrap', 'Il controllo nativo mostra sempre il segnaposto `gg/mm/aaaa` del browser: la label del `form-floating` resta alzata anche a campo vuoto.')
    ->note('wonder', 'Il contenitore ha la classe `date` come il `DatePicker`, ma non porta `data-wi-date-picker`: la lib non apre nessun calendario proprio e lascia fare al browser.')
    ->example('Base', <<<'PHP'
    (new Date('birth_date'))
        ->label('Data di nascita')
    PHP, 'Il browser mostra il proprio selettore e invia il valore in formato `Y-m-d`.')
    ->example('Valore e intervallo ammesso', <<<'PHP'
    (new Date('event_date'))
        ->label('Data evento')
        ->value('2026-10-15')
        ->min('2026-10-01')
        ->max('2026-12-31')
        ->required()
    PHP, '`value()`, `min()` e `max()` vogliono il formato `Y-m-d`: il browser blocca le date fuori dall\'intervallo e `required()` aggiunge l\'asterisco alla label.')
    ->example('Con errore di validazione', <<<'PHP'
    (new Date('start_date'))
        ->label('Inizio')
        ->value('2026-09-01')
        ->error('La data di inizio deve seguire quella di oggi.')
    PHP, 'Il testo di `error()` segna il campo come non valido e compare sotto l\'input.')
    ->example('Due date affiancate', <<<'PHP'
    (new Form())->columns(2)->gap(3)->components([
        (new Date('check_in'))->label('Arrivo')->value('2026-10-10'),
        (new Date('check_out'))->label('Partenza')->value('2026-10-12'),
    ])
    PHP, 'Un `Form` con `columns(2)` dispone i campi su due colonne in entrambi i temi.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('birth_date')->textDate()->label('Data di nascita')->value('05/10/1990')->dateMax('2026-10-08')
    PHP, '`textDate()` costruisce questo Element e normalizza il valore a `Y-m-d` anche se arriva scritto all\'italiana; `dateMin()` / `dateMax()` finiscono in `min()` / `max()`.');
