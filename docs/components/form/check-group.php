<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\CheckGroup;

return ComponentDoc::for(CheckGroup::class)
    ->title('CheckGroup')
    ->group('choice')
    ->order(40)
    ->tags('checkbox', 'radio', 'gruppo', 'opzioni', 'pillole', 'lista')
    ->description('Un gruppo di caselle (`inputType(\'checkbox\')`, il default) o di radio (`inputType(\'radio\')`) su una lista statica di `options()`. Con le caselle il nome diventa `nome[]` e il valore è un array; con le radio resta singolo. Nel backend le voci stanno in un riquadro che scorre, con la barra di ricerca di `searchBar()`, oppure in linea come pillole con `pills()`; un\'opzione estesa (`[\'name\' => ..., \'child\' => [...], \'filter\' => [...]]`) può avere sotto-voci e un segno (`icon`, `color`, `image`). Nel frontend è la `wi-checkbox-list` della lib, senza ricerca, pillole né sotto-voci. Nelle Resource: `FormField::key(\'nome\')->checkbox()->options([...])` e `->radio([...])`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#scelta', 'FormField: scelta')
    ->docs('concetti/form/form-field.md#spunte-a-pillole', 'Spunte a pillole')
    ->docs('concetti/form/form-field.md#opzioni-con-un-segno', 'Opzioni con un segno')
    ->related('checkbox', 'check-tree', 'dynamic-check', 'select')
    ->note('bootstrap', 'Il riquadro è alto 120px e scorre; le pillole (`pills()`) sono `btn-check` con label `btn-outline-secondary` e servono agli elenchi corti. Le voci `child` escono indentate sotto il genitore.')
    ->note('wonder', 'Il contenitore ha sempre la classe `checkbox`, anche per le radio: la differenza la fa il tipo dell\'`<input>`. Il confronto con `value()` è stretto: con chiavi numeriche passa i valori dello stesso tipo delle chiavi.')
    ->example('Base', <<<'PHP'
    (new CheckGroup('sizes'))
        ->label('Taglie disponibili')
        ->options(['s' => 'S', 'm' => 'M', 'l' => 'L', 'xl' => 'XL'])
        ->value(['m', 'l'])
    PHP, 'Un gruppo di caselle: il nome esce `sizes[]` e un campo nascosto vuoto precede la lista, così il form posta la chiave anche senza spunte.')
    ->example('Radio', <<<'PHP'
    (new CheckGroup('shipping'))
        ->label('Spedizione')
        ->inputType('radio')
        ->options(['standard' => 'Standard (3-5 giorni)', 'express' => 'Espressa (24 ore)', 'pickup' => 'Ritiro in negozio'])
        ->value('express')
        ->required()
    PHP, 'Con `inputType(\'radio\')` il valore è singolo e il nome resta `shipping`.')
    ->example(
        Example::make('Pillole e opzioni con un segno')
            ->code(<<<'PHP'
            [
                (new CheckGroup('colors'))
                    ->label('Colori')
                    ->options([
                        'red' => ['name' => 'Rosso', 'color' => '#c00'],
                        'blue' => ['name' => 'Blu', 'color' => '#06c'],
                        'water' => ['name' => 'Impermeabile', 'icon' => 'bi-droplet'],
                        'plain' => 'Tinta unita',
                    ])
                    ->pills()
                    ->value(['red']),
                (new CheckGroup('preferred'))
                    ->inputType('radio')
                    ->options(['7' => 'Preferito', '9' => 'Riserva'])
                    ->pills()
                    ->label('')
                    ->value('7'),
            ]
            PHP)
            ->description('`pills()` mette le voci in linea, senza il riquadro che scorre; con `label(\'\')` non resta un titolo sopra. Il segno di un\'opzione (`color`, `icon` o `image`) esce davanti al nome.')
            ->themes('bootstrap')
    )
    ->example(
        Example::make('Con ricerca e sotto-voci')
            ->code(<<<'PHP'
            (new CheckGroup('categories'))
                ->label('Categorie')
                ->searchBar()
                ->options([
                    'clothing' => ['name' => 'Abbigliamento', 'child' => [
                        'tshirt' => 'Magliette',
                        'hoodie' => 'Felpe',
                    ]],
                    'shoes' => ['name' => 'Scarpe', 'child' => [
                        'sneakers' => 'Sneakers',
                        'boots' => 'Stivali',
                    ]],
                    'accessories' => 'Accessori',
                ])
                ->value(['hoodie', 'accessories'])
            PHP)
            ->description('`searchBar()` aggiunge il campo di ricerca in testa al riquadro (`data-wi-search`); le voci `child` escono indentate. Per alberi profondi preferisci `CheckTree`.')
            ->themes('bootstrap')
    )
    ->example('Dal DSL delle Resource', <<<'PHP'
    [
        FormField::key('sizes')
            ->checkbox()
            ->options(['s' => 'S', 'm' => 'M', 'l' => 'L'])
            ->label('Taglie')
            ->value(['s']),
        FormField::key('shipping')
            ->radio(['standard' => 'Standard', 'express' => 'Espressa'])
            ->label('Spedizione')
            ->value('standard'),
    ]
    PHP, 'Gli stessi gruppi dichiarati come in `Resource::formSchema()`: `checkbox()` con `options()` e `radio()` costruiscono entrambi questo `CheckGroup`; nel backend `pills()` e `searchBar()` si aggiungono in coda.');
