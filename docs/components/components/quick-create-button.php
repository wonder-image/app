<?php

use Wonder\App\Resources\Contacts\ContactResource;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\QuickCreateButton;

return ComponentDoc::for(QuickCreateButton::class)
    ->title('QuickCreateButton')
    ->group('action')
    ->order(50)
    ->tags('creazione rapida', 'modal', 'foreign key', 'resource')
    ->description('Il bottone «+» accanto a un campo chiave esterna: apre una finestra con i campi di un\'altra Resource e la crea senza lasciare la scheda. `fields()` limita i campi mostrati, `label()` indica quale campo diventa l\'etichetta della nuova opzione, `layout()` disegna il corpo della finestra.')
    ->uses(ContactResource::class)
    ->docs('concetti/form/quick-create.md', 'Creazione rapida da campo FK')
    ->related('modal', 'button')
    ->note('bootstrap', 'La finestra chiama l\'API `resource/quick-create/` del backend come utente `@system`: nel catalogo il bottone si rende ma la creazione non avviene.')
    ->unsupported('wonder', 'Il renderer Wonder restituisce una stringa vuota di proposito: il bottone esiste solo nel backend, e una scheda condivisa fra i due temi resta in piedi.')
    ->example('Base', <<<'PHP'
    QuickCreateButton::make(ContactResource::class)
        ->text('Nuovo contatto')
    PHP)
    ->example('Campi limitati e dimensione', <<<'PHP'
    QuickCreateButton::make(ContactResource::class)
        ->text('Aggiungi')
        ->fields(['name', 'surname', 'email'])
        ->label('name')
        ->size('sm')
    PHP);
