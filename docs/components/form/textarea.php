<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\Textarea;

return ComponentDoc::for(Textarea::class)
    ->title('Textarea')
    ->group('text')
    ->order(110)
    ->tags('textarea', 'testo', 'righe', 'descrizione', 'contatore')
    ->description('L\'area di testo a più righe. `rows()` scrive l\'attributo nativo, `maxLength()` dichiara il limite e `counter()` chiede alla lib di contare i caratteri (`data-wi-counter`). Nel backend il `<textarea>` sta nel `form-floating` con un\'altezza fissa di 100px e, con `maxLength()`, un contatore «n / max» in basso a destra; nel frontend è il `wi-input-container textarea` della lib, senza contatore. Nelle Resource si dichiara con `textarea()`; con una versione diventa il `TextareaEditor`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#area-di-testo', 'FormField: area di testo')
    ->related('textarea-editor', 'input-text')
    ->note('bootstrap', 'Il renderer scrive `style="height: 100px"` e il placeholder uguale alla label: uno `style` dato al campo viene ignorato. `maxLength()` non emette l\'attributo `maxlength`: il limite è solo il contatore, che la lib aggiorna quando c\'è `counter()`.')
    ->note('wonder', '`maxLength()` e `counter()` non cambiano il markup: il `<textarea>` esce con la sola label galleggiante e l\'altezza del CSS della lib.')
    ->example('Base', <<<'PHP'
    (new Textarea('description'))
        ->label('Descrizione')
    PHP)
    ->example('Con valore e righe', <<<'PHP'
    (new Textarea('notes'))
        ->label('Note')
        ->rows(6)
        ->value("Consegna al piano.\nCitofonare Rossi.")
    PHP, 'Gli a capo del valore restano nel testo; `rows()` finisce nell\'attributo, ma l\'altezza la decide comunque il tema.')
    ->example('Con limite e contatore', <<<'PHP'
    (new Textarea('excerpt'))
        ->label('Sommario')
        ->maxLength(160)
        ->counter()
        ->value('Un riassunto breve per la scheda del prodotto.')
    PHP, 'Nel backend il contatore parte dalla lunghezza del valore; `counter()` lo fa aggiornare mentre si scrive.')
    ->example('Con errore', <<<'PHP'
    (new Textarea('description'))
        ->label('Descrizione')
        ->required()
        ->error('La descrizione è obbligatoria.')
    PHP)
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('description')->textarea()->label('Descrizione')->required()
    PHP, '`textarea()` senza versione ritorna `Inputs\InputTextarea` e costruisce questo Element; `textarea(\'plus\')` costruisce invece il `TextareaEditor`.');
