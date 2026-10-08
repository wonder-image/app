<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\SearchRemote;

return ComponentDoc::for(SearchRemote::class)
    ->title('SearchRemote')
    ->group('advanced')
    ->order(20)
    ->tags('ricerca', 'ajax', 'remoto', 'autocomplete')
    ->description('Un campo di ricerca con la tendina dei risultati caricata via AJAX dall\'URL di `url()`. `searchType(\'text\')` è la ricerca libera, `searchType(\'radio\')` la scelta singola che posta il valore scelto; lo script client legge `data-wi-search-<tipo>`. Nelle Resource: `FormField::key(\'nome\')->searchText($url)` e `->searchRadio($url)`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->related('dynamic-check', 'select')
    ->note('bootstrap', 'Nel catalogo l\'URL non risponde: si vede il campo, non i risultati.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new SearchRemote('q'))
                ->label('Cerca un prodotto')
                ->url('/api/products/search/')
                ->attr('placeholder', 'Nome o codice')
            PHP)
            ->height(120)
    )
    ->example(
        Example::make('Scelta singola')
            ->code(<<<'PHP'
            (new SearchRemote('customer_id'))
                ->label('Cliente')
                ->url('/api/backend/customers/search/')
                ->searchType('radio')
            PHP)
            ->height(120)
    )
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('customer_id')->searchRadio('/api/backend/customers/search/')->label('Cliente')
    PHP);
