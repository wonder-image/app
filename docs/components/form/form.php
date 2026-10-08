<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\InputEmail;
use Wonder\Elements\Form\Components\InputText;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Components\Textarea;
use Wonder\Elements\Form\Form;

return ComponentDoc::for(Form::class)
    ->title('Form')
    ->group('structure')
    ->order(10)
    ->tags('form', 'contenitore', 'griglia', 'csrf')
    ->description('Il contenitore `<form>` dei campi: `components()` elenca i figli, `columns()` e `gap()` disegnano la griglia, `noFloating()` toglie la label galleggiante a tutti i figli che non decidono da soli. Entrambi i temi stampano il token CSRF quando c\'è una sessione attiva.')
    ->uses(InputEmail::class, InputText::class, Submit::class, Textarea::class)
    ->docs('concetti/form/README.md', 'Form')
    ->docs('concetti/form/csrf.md', 'Token CSRF')
    ->related('input-text', 'submit', 'card')
    ->note('bootstrap', 'Il form è `row d-grid row-col-N g-N` e porta `onsubmit="loadingSpinner()"`.')
    ->note('wonder', 'Il form è `wi-form`; le classi di griglia che riceve sono quelle di Bootstrap (`row-col-*`, `g-*`) e la lib non le definisce: in una pagina vera i campi stanno nella griglia del sito.')
    ->example('Base', <<<'PHP'
    (new Form())->components([
        (new InputText('name'))->label('Nome')->required(),
        (new InputEmail('email'))->label('Email')->required(),
        (new Textarea('message'))->label('Messaggio'),
        new Submit(),
    ])
    PHP)
    ->example('Griglia a due colonne', <<<'PHP'
    (new Form())->columns(2)->gap(3)->components([
        (new InputText('name'))->label('Nome'),
        (new InputText('surname'))->label('Cognome'),
        (new InputEmail('email'))->label('Email')->columnSpan(2),
    ])
    PHP, 'Ogni figlio può occupare più colonne con `columnSpan()`.')
    ->example('Senza label galleggianti', <<<'PHP'
    (new Form())->noFloating()->components([
        (new InputText('city'))->label('Città'),
        (new InputText('zip'))->label('CAP')->noFloating(false),
    ])
    PHP, 'Il form propaga `noFloating()`; il campo «CAP» la rimette con `noFloating(false)`.');
