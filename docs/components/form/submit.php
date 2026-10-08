<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\Submit;
use Wonder\Elements\Form\Form;
use Wonder\Elements\Form\Components\InputEmail;

return ComponentDoc::for(Submit::class)
    ->title('Submit')
    ->group('action')
    ->order(20)
    ->tags('invia', 'submit', 'salva', 'bottone')
    ->description('Il bottone che invia il form. Il nome di default è `upload`: in un layout di Resource sostituisce il «Salva» del piè di pagina. `buttonClass()` e `addButtonClass()` lavorano sul bottone, `class()` e `addClass()` sul contenitore; `onclick()` aggiunge un callback.')
    ->uses(Form::class, InputEmail::class)
    ->docs('concetti/form/save-bar.md', 'Barra di salvataggio')
    ->related('form', 'form-button', 'button')
    ->example('Base', <<<'PHP'
    (new Submit())->label('Salva')
    PHP)
    ->example('In un form', <<<'PHP'
    (new Form())->components([
        (new InputEmail('email'))->label('Email')->required(),
        (new Submit('subscribe'))->label('Iscriviti')->buttonClass('btn btn-success w-100'),
    ])
    PHP, 'Con `buttonClass()` la classe del bottone è tutta tua; `addButtonClass()` aggiunge a quella del tema.');
