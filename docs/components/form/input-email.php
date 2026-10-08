<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputEmail;

return ComponentDoc::for(InputEmail::class)
    ->title('Input email')
    ->group('text')
    ->order(20)
    ->tags('email', 'posta', 'indirizzo', 'input')
    ->description('Il campo per un indirizzo email: un `InputText` con `type="email"`, che il browser controlla prima dell\'invio e che sul telefono apre la tastiera con la chiocciola. È l\'unico tipo per cui `autocomplete()` senza argomenti scrive `autocomplete="email"` invece di `"on"`. Nei due temi rende come il campo di testo: `form-floating` nel backend, `wi-input-container email` con la label galleggiante nel frontend.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: campi di testo')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('input-text', 'input-tel', 'input-url')
    ->example('Base', <<<'PHP'
    (new InputEmail('email'))
        ->label('Email')
    PHP)
    ->example('Obbligatorio con autocomplete', <<<'PHP'
    (new InputEmail('email'))
        ->label('Email')
        ->value('mario.rossi@esempio.it')
        ->required()
        ->autocomplete()
    PHP, '`autocomplete()` scrive `autocomplete="email"`, `autocomplete(false)` lo spegne con `"off"`; `required()` aggiunge l\'asterisco alla label.')
    ->example('Con errore di validazione', <<<'PHP'
    (new InputEmail('email'))
        ->label('Email')
        ->value('mario.rossi@')
        ->error('Inserisci un indirizzo email completo.')
    PHP, 'Il controllo del browser non basta: il testo di `error()` è quello che il server rimanda dopo un invio non valido.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('email')->email()->label('Email')->required()->autocomplete()
    PHP, '`email()` ritorna `Inputs\InputEmail`, che costruisce questo stesso Element; anche qui `autocomplete()` vale `"email"`.');
