<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\App\ResourceSchema\RepeaterColumn;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\Repeater;

return ComponentDoc::for(Repeater::class)
    ->title('Repeater')
    ->group('advanced')
    ->order(10)
    ->tags('righe', 'ripetibile', 'tabella', 'relazione', 'ordinabile')
    ->description('Le righe ripetibili di un form: ogni riga ha le colonne dichiarate con `RepeaterColumn` (testo, numero, prezzo, select, file, colore, ...), si aggiunge, si riordina e si elimina con il JavaScript che il renderer porta con sé. Si dichiara con `FormField::key(\'nome\')->repeater([...colonne])`; `Wonder\\App\\Support\\Repeater` legge le righe dalla richiesta e sincronizza la relazione. Le righe nuove passano da `setInput()` della lib, così i campi che diventano widget (file, editor, select con ricerca) sono uguali a quelli esistenti.')
    ->uses(FormField::class, RepeaterColumn::class)
    ->docs('concetti/form/repeater.md', 'Repeater')
    ->related('sortable-input', 'form-button')
    ->note('bootstrap', 'Esiste solo nel backend: il frontend non rende repeater e `render(\'wonder\')` lancia un\'eccezione.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            FormField::key('variants')
                ->repeater([
                    RepeaterColumn::key('title')->text()->label('Variante')->required()->columnSpan(8),
                    RepeaterColumn::key('position')->number()->label('Posizione')->columnSpan(4),
                ])
                ->label('Varianti')
                ->value([
                    ['title' => 'Rossa', 'position' => 1],
                    ['title' => 'Blu', 'position' => 2],
                ])
            PHP)
            ->description('Le colonne si dividono la riga con `columnSpan()` su 12; `value()` sono le righe già salvate.')
            ->height(320)
    )
    ->example(
        Example::make('Colonne di tipo diverso')
            ->code(<<<'PHP'
            FormField::key('options')
                ->repeater([
                    RepeaterColumn::key('option')->text()->label('Opzione')->columnSpan(5),
                    RepeaterColumn::key('price')->price()->label('Prezzo')->columnSpan(3),
                    RepeaterColumn::key('color')->color()->label('Colore')->columnSpan(2),
                    RepeaterColumn::key('visible')->toggle()->label('Visibile')->columnSpan(2),
                ])
                ->label('Opzioni')
            PHP)
            ->height(260)
    )
    ->example(
        Example::make('Ordinabile, con etichette proprie')
            ->code(<<<'PHP'
            FormField::key('allowed_domains')
                ->repeater([
                    RepeaterColumn::key('domain')->text()->label('Dominio')->columnSpan(12),
                ])
                ->repeaterSortable()
                ->repeaterAddLabel('Aggiungi dominio')
                ->repeaterDeleteTitle('Togliere il dominio?')
                ->label('Domini consentiti')
                ->value([['domain' => 'wonderimage.it'], ['domain' => 'example.com']])
            PHP)
            ->description('`repeaterSortable()` aggiunge la maniglia di trascinamento; i metodi `repeater*()` di `InputRepeater` governano etichette, conferma di eliminazione, raggruppamenti e colonne avanzate.')
            ->height(300)
    );
