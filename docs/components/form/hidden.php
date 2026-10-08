<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Code;
use Wonder\Elements\Form\Components\Hidden;
use Wonder\Elements\Form\Components\InputText;
use Wonder\Elements\Form\Form;

return ComponentDoc::for(Hidden::class)
    ->title('Campo nascosto')
    ->group('structure')
    ->order(20)
    ->tags('hidden', 'nascosto', 'id', 'token', 'post')
    ->description('Un `<input type="hidden">`: trasporta un valore nel POST senza interfaccia. Non ha label, contenitore né errore in nessuno dei due temi: il renderer scrive il solo tag con `name`, `value` e gli attributi dati con `attr()`, quindi le anteprime qui sotto sono vuote per natura.

Nelle Resource si dichiara con `FormField::key(\'nome\')->hidden()`; nei layout del backend sta direttamente nella riga, senza occupare una colonna, e dentro un repeater la colonna `hidden` non prende spazio.')
    ->uses(Code::class, Form::class, FormField::class, InputText::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->docs('concetti/form/csrf.md', 'Token CSRF')
    ->related('form', 'input-text')
    ->note('bootstrap', 'Il renderer ridefinisce `render()` e salta `form-floating`, label ed errore: esce il solo `<input>`, anche dentro un `Form`.')
    ->note('wonder', 'Niente `wi-input-container` né `data-wi-label`: il tag porta solo `data-wi-check="true"` come ogni Field, ma la label galleggiante non c\'è.')
    ->example('Base', <<<'PHP'
    (new Hidden('id'))
        ->value('12')
    PHP, 'L\'anteprima è vuota: il campo non ha niente da mostrare, ma `id=12` viaggia nel POST.')
    ->example('Il markup generato', <<<'PHP'
    $field = (new Hidden('id'))->value('12')->attr('data-row', 'first');

    Code::make($field->render(), 'html')
    PHP, 'Lo stesso campo visto attraverso `Code`: un solo tag, con gli attributi di `attr()` dopo `name` e `value`.')
    ->example('Dentro un form', <<<'PHP'
    (new Form())->components([
        (new Hidden('id'))->value('12'),
        (new Hidden('redirect'))->value('/backend/articles/'),
        (new InputText('title'))->label('Titolo')->value('Primo articolo'),
    ])
    PHP, 'I campi nascosti stanno nel `<form>` accanto agli altri, senza occupare spazio; il `Form` aggiunge da solo il token CSRF quando c\'è una sessione.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('id')->hidden()->value(12)
    PHP, '`hidden()` costruisce questo Element con i soli modificatori universali (`value()`, `attribute()`, `visibleWhen()`...).');
