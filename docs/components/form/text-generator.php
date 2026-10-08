<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\TextGenerator;

return ComponentDoc::for(TextGenerator::class)
    ->title('Testo generato')
    ->group('text')
    ->order(140)
    ->tags('codice', 'slug', 'genera', 'casuale', 'input')
    ->description('Un campo di testo con un bottone «GENERA» che lo riempie con un codice casuale: al click la lib chiama `generateCode(\'#id\')`, o la funzione JS globale indicata con `callback()`, passandole il selettore dell\'input. Serve nel backend per slug, codici sconto e chiavi; `buttonLabel()` cambia il testo del bottone. Esiste solo nel tema Bootstrap: il bottone sta dentro il `form-floating`, allineato a destra sull\'input.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: campi di testo')
    ->related('input-text')
    ->note('bootstrap', 'Il bottone è un `div.btn.btn-sm.btn-dark` in `position-absolute` dentro il `form-floating`, con `onclick="<callback>(\'#<id>\')"`: la funzione deve esistere come globale nella pagina.')
    ->example('Base', <<<'PHP'
    (new TextGenerator('code'))
        ->label('Codice')
    PHP, 'Il click chiama `generateCode()` della lib, che scrive nel campo un codice casuale.')
    ->example('Etichetta del bottone e valore', <<<'PHP'
    (new TextGenerator('coupon'))
        ->label('Codice sconto')
        ->value('ESTATE24')
        ->buttonLabel('Rigenera')
        ->required()
    PHP, 'Il valore salvato resta modificabile a mano; il bottone lo sostituisce con uno nuovo.')
    ->example('Callback personalizzata', <<<'PHP'
    (new TextGenerator('slug'))
        ->label('Slug')
        ->callback('generateCode')
        ->buttonLabel('Crea')
    PHP, '`callback()` è il nome di una funzione JS globale che riceve il selettore dell\'input (`\'#field_xxx\'`): qui si usa ancora `generateCode`, in un sito può essere una funzione del tema che costruisce lo slug dal titolo.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('code')->textGenerator(null, 'Genera')->label('Codice')
    PHP, '`textGenerator($callback, $buttonLabel)` ritorna `Inputs\InputTextGenerator`, con `callback()` e `buttonLabel()` anche a catena.');
