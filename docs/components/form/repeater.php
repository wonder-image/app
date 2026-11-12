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
    ->description('Le righe ripetibili di un form: ogni riga ha le colonne dichiarate con `RepeaterColumn` (testo, numero, prezzo, select, file, colore, ...), si aggiunge, si riordina e si elimina (con `repeaterMinRows($n)` le ultime `$n` righe non si eliminano) con il JavaScript che il renderer porta con sé. Si dichiara con `FormField::key(\'nome\')->repeater([...colonne])`; `Wonder\\App\\Support\\Repeater` legge le righe dalla richiesta e sincronizza la relazione. Le righe nuove passano da `setInput()` della lib, così i campi che diventano widget (file, editor, select con ricerca) sono uguali a quelli esistenti.')
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
            ->description('`repeaterSortable()` aggiunge la maniglia a sinistra della riga: si trascina, o si sposta con le frecce della tastiera; a destra resta il cestino; i metodi `repeater*()` di `InputRepeater` governano etichette, conferma di eliminazione, raggruppamenti e colonne avanzate.')
            ->height(300)
    )
    ->example(
        Example::make('Righe che non si aggiungono né si tolgono')
            ->code(<<<'PHP'
            FormField::key('addresses')
                ->repeater([
                    RepeaterColumn::key('company')->text()->readonly()->label('Azienda')->columnSpan(4),
                    RepeaterColumn::key('street')->text()->label('Via')->columnSpan(7),
                ])
                ->repeaterAddButton(false)
                ->repeaterDeleteButton(false)
                ->label('Indirizzi')
                ->value([
                    ['company' => 'Rossi srl', 'street' => 'Via Roma 1'],
                    ['company' => 'Bianchi spa', 'street' => 'Via Milano 12'],
                ])
            PHP)
            ->description('`repeaterAddButton(false)` toglie il bottone che aggiunge, `repeaterDeleteButton(false)` il cestino di ogni riga: per le righe che arrivano da altrove e qui si correggono soltanto. I due si spengono anche uno per volta.')
            ->height(300)
    )
    ->example(
        Example::make('Schede richiudibili')
            ->code(<<<'PHP'
            FormField::key('people')
                ->repeater([
                    RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(6),
                    RepeaterColumn::key('job_title')->text()->label('Mansione')->columnSpan(5),
                    RepeaterColumn::key('email')->email()->label('Email')->columnSpan(11),
                ])
                ->repeaterCollapsible('name', ['job_title', 'email'])
                ->repeaterCollapsibleEmptyLabel('Nuovo referente')
                ->repeaterAddLabel('Aggiungi referente')
                ->label('Referenti')
                ->value([
                    ['name' => 'Giulia Bianchi', 'job_title' => 'Acquisti', 'email' => 'giulia@example.com'],
                    ['name' => 'Marco Verdi', 'job_title' => 'Amministrazione', 'email' => 'marco@example.com'],
                ])
            PHP)
            ->description('`repeaterCollapsible($titolo, $sottotitolo)` chiude le righe salvate su un riassunto; un clic le apre. Le righe nuove nascono aperte, e i campi di una riga chiusa vengono postati lo stesso.')
            ->height(360)
    )
    ->example(
        Example::make('Repeater dentro una riga')
            ->code(<<<'PHP'
            FormField::key('contact_people')
                ->repeater([
                    RepeaterColumn::key('name')->text()->label('Nome')->columnSpan(12),
                    RepeaterColumn::key('points')
                        ->repeater([
                            RepeaterColumn::key('kind')
                                ->select(['email' => 'Email', 'phone' => 'Telefono'])
                                ->label('Tipo')->columnSpan(3),
                            RepeaterColumn::key('email')->email()->label('Email')
                                ->columnFill()->visibleWhen('kind', 'email'),
                            RepeaterColumn::key('phone')->text()->label('Telefono')
                                ->columnFill()->visibleWhen('kind', 'phone'),
                        ])
                        ->label('Recapiti')
                        ->repeaterAddLabel('Aggiungi recapito')
                        ->columnSpan(12),
                ])
                ->nested()
                ->repeaterAddLabel('Aggiungi referente')
                ->label('Referenti')
                ->value([
                    'row_1' => ['name' => 'Giulia Bianchi', 'points' => [
                        'row_1' => ['kind' => 'email', 'email' => 'giulia@example.com'],
                        'row_2' => ['kind' => 'phone', 'phone' => '0212345678'],
                    ]],
                ])
            PHP)
            ->description('Una colonna può essere un repeater: `RepeaterColumn::key(...)->repeater([...])`, con `nested()` su quello esterno. I campi si chiamano `contact_people[RIGA][points][RIGA_INTERNA][kind]`; il repeater interno parte vuoto, tiene solo etichetta, larghezza, bottone e testi di eliminazione, e non ne contiene un altro. Solo tema Bootstrap.')
            ->height(460)
    );
