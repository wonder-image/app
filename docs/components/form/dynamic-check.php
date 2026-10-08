<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\DynamicCheck;

return ComponentDoc::for(DynamicCheck::class)
    ->title('DynamicCheck')
    ->group('choice')
    ->order(80)
    ->tags('ricerca', 'ajax', 'many-to-many', 'checkbox', 'dinamico')
    ->description('Un gruppo di spunte senza lista statica: un campo di ricerca interroga l\'URL di `url()` e popola le voci lato client. Serve alle associazioni molti-a-molti con tante righe (utenti, prodotti, categorie). `inputType(\'radio\')` lo rende a scelta singola. Nelle Resource: `FormField::key(\'nome\')->dynamicCheck($url)`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->related('check-group', 'check-tree', 'search-remote')
    ->note('bootstrap', 'Nel catalogo l\'URL non risponde: si vede il campo di ricerca con le voci già scelte, non i risultati.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new DynamicCheck('products'))
                ->label('Prodotti collegati')
                ->url('/api/backend/products/search/')
                ->value([12, 31])
            PHP)
            ->height(200)
    )
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('users')->dynamicCheck('/api/backend/users/search/', 'radio')->label('Responsabile')
    PHP);
