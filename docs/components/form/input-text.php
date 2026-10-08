<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\InputText;
use Wonder\Elements\Form\Form;

return ComponentDoc::for(InputText::class)
    ->title('Input testo')
    ->group('text')
    ->order(10)
    ->tags('testo', 'text', 'campo', 'input')
    ->description('Il campo di testo a riga singola. Nel backend usa il `form-floating` di Bootstrap, nel frontend il `wi-input-container` della lib con la label che galleggia. Nelle Resource si dichiara con `FormField::key(\'nome\')->text()`, che costruisce questo stesso Element.')
    ->uses(Form::class, FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('input-email', 'input-password', 'textarea', 'form')
    ->note('bootstrap', 'Con `noFloating()` il renderer Bootstrap salta il `form-floating` e, oggi, non stampa la label: usa il placeholder o lascia la label galleggiante.')
    ->note('wonder', 'Il `Form` del tema Wonder non mette spazio fra i campi: in una pagina vera i campi stanno nella griglia del sito (`col-*`, `gap-*` della lib).')
    ->example('Base', <<<'PHP'
    (new InputText('name'))
        ->label('Nome')
        ->placeholder('Mario Rossi')
    PHP)
    ->example('Valore, obbligatorio e limiti', <<<'PHP'
    (new InputText('nickname'))
        ->label('Nickname')
        ->value('mario.rossi')
        ->required()
        ->minLength(3)
        ->maxLength(20)
    PHP, '`required()`, `readonly()` e `disabled()` scrivono gli attributi nativi; `maxLength()` aggiunge anche il contatore dove il tema lo prevede.')
    ->example('Con errore di validazione', <<<'PHP'
    (new InputText('email_alias'))
        ->label('Alias')
        ->value('non valido!')
        ->error('Usa solo lettere, numeri e punti.')
    PHP, 'Il testo passato a `error()` segna il campo come non valido e compare sotto l\'input.')
    ->example(
        Example::make('Senza label galleggiante')
            ->code(<<<'PHP'
            (new InputText('city'))
                ->label('Città')
                ->noFloating()
            PHP)
            ->description('La label resta ferma sopra il campo (`wi-nf` nel tema Wonder). Dentro un `Form`, `noFloating()` sul form vale per tutti i figli; il singolo campo lo ridiscute con `noFloating(false)`.')
            ->themes('wonder')
    )
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('name')->text()->label('Nome')->required()->placeholder('Mario Rossi')
    PHP, 'Lo stesso campo dichiarato come in `Resource::formSchema()`: `FormField` costruisce l\'`InputText` e lo rende con il tema attivo.');
