<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\Select;

return ComponentDoc::for(Select::class)
    ->title('Select')
    ->group('choice')
    ->order(10)
    ->tags('select', 'tendina', 'opzioni', 'select2', 'multiplo', 'ricerca')
    ->description('La tendina a scelta singola o multipla. `options()` prende un array `valore => etichetta` (o `valore => [\'name\' => ..., \'filter\' => [...]]`), `placeholder()` aggiunge la voce vuota in testa, `attr(\'multiple\', true)` ammette più scelte. Nel backend è un `form-select` in `form-floating`, che Select2 potenzia con la ricerca quando porta `data-wi-select-search`; nel frontend è il `<select>` nativo dentro `wi-input-container select` con `data-wi-select`, che la lib veste con il proprio stile. Nelle Resource si dichiara con `FormField::key(\'nome\')->select([...])` o `->selectSearch([...])`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#scelta', 'FormField: scelta')
    ->docs('concetti/form/form-field.md#opzioni-con-un-segno', 'Opzioni con un segno')
    ->related('select-old', 'check-group', 'text-list', 'search-remote')
    ->note('bootstrap', '`legacyContainer()` mette la label sopra il select (`wi-container-select`) al posto del `form-floating`. Icona, colore e immagine di un\'opzione (`icon`, `color`, `image` accanto a `name`) viaggiano in `data-wi-*` sulla `<option>` e li disegna Select2.')
    ->note('wonder', 'Il contenitore ha sempre la classe `compiled`, perché lo script di stile della lib la aspetta come stato di partenza. `legacyContainer()` e i segni delle opzioni non sono letti.')
    ->example('Base', <<<'PHP'
    (new Select('status'))
        ->label('Stato')
        ->options(['draft' => 'Bozza', 'published' => 'Pubblicato', 'archived' => 'Archiviato'])
        ->value('published')
    PHP, 'La voce con lo stesso valore di `value()` esce `selected`.')
    ->example('Voce vuota, obbligatorio e scelta multipla', <<<'PHP'
    [
        (new Select('category'))
            ->label('Categoria')
            ->options(['clothing' => 'Abbigliamento', 'shoes' => 'Scarpe', 'accessories' => 'Accessori'])
            ->placeholder('Seleziona una categoria')
            ->required(),
        (new Select('tags'))
            ->label('Tag')
            ->options(['new' => 'Novità', 'sale' => 'Saldi', 'eco' => 'Eco'])
            ->attr('multiple', true)
            ->value(['new', 'eco']),
    ]
    PHP, '`placeholder()` antepone una `<option value="">` alle opzioni già date. Con `multiple` il nome diventa `tags[]` e un campo nascosto con lo stesso nome precede il select, così il form posta qualcosa anche senza scelte.')
    ->example(
        Example::make('Con ricerca')
            ->code(<<<'PHP'
            (new Select('city'))
                ->label('Città')
                ->options([
                    'mi' => 'Milano', 'rm' => 'Roma', 'to' => 'Torino', 'na' => 'Napoli',
                    'fi' => 'Firenze', 'bo' => 'Bologna', 'ba' => 'Bari', 've' => 'Venezia',
                ])
                ->attr('data-wi-select-search', 'true')
                ->placeholder('Cerca una città')
            PHP)
            ->description('`data-wi-select-search` chiede alla lib la tendina con il campo di ricerca: Select2 nel backend, la lista filtrabile della lib nel frontend. È lo stesso attributo che scrive `FormField::selectSearch()`.')
            ->height(360)
    )
    ->example(
        Example::make('Label sopra e opzioni con un segno')
            ->code(<<<'PHP'
            [
                (new Select('order'))
                    ->label('Ordinamento')
                    ->options(['date' => 'Per data', 'name' => 'Per nome'])
                    ->legacyContainer(),
                (new Select('color'))
                    ->label('Colore')
                    ->options([
                        'red' => ['name' => 'Rosso', 'color' => '#c00'],
                        'blue' => ['name' => 'Blu', 'color' => '#06c'],
                        'water' => ['name' => 'Impermeabile', 'icon' => 'bi-droplet'],
                    ])
                    ->attr('data-wi-select-search', 'true'),
            ]
            PHP)
            ->description('`legacyContainer()` è il markup delle vecchie pagine di filtro, con la label sopra il select. Un\'opzione può portare `color`, `icon` o `image`: il select li passa in `data-wi-*` e Select2 li disegna davanti al nome.')
            ->themes('bootstrap')
            ->height(360)
    )
    ->example('Dal DSL delle Resource', <<<'PHP'
    [
        FormField::key('status')
            ->select(['draft' => 'Bozza', 'published' => 'Pubblicato'])
            ->label('Stato')
            ->value('draft')
            ->required(),
        FormField::key('tags')
            ->selectSearch(['new' => 'Novità', 'sale' => 'Saldi', 'eco' => 'Eco'], true)
            ->label('Tag')
            ->value(['sale']),
    ]
    PHP, 'Gli stessi campi dichiarati come in `Resource::formSchema()`: `select()` costruisce questo `Select`, `selectSearch($opzioni, $multiple)` aggiunge `data-wi-select-search` e, con il secondo argomento, `multiple`.');
