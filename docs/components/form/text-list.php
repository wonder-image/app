<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\TextList;

return ComponentDoc::for(TextList::class)
    ->title('Testo con lista')
    ->group('text')
    ->order(130)
    ->tags('combobox', 'lista', 'autocompletamento', 'ricerca', 'opzioni', 'select')
    ->description('Un campo di testo con una lista di opzioni statiche (`[valore => etichetta]`) che si filtra mentre si scrive: la via di mezzo fra `Select` e la ricerca remota di `SearchRemote`, per liste lunghe che non hanno bisogno del server. Nel frontend è il combobox della lib: l\'input mostra l\'etichetta scelta e un radio nascosto per opzione trasporta il valore nel form. Nel backend degrada a un `<select>` ricercabile (`data-wi-select-search`, Select2) con lo stesso markup del `Select`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#scelta', 'FormField: campi di scelta')
    ->related('select', 'search-remote', 'input-text')
    ->note('wonder', 'L\'input ha `data-wi-list-input` e la lista di validazione `data-wi-list-array` (le etichette in minuscolo separate da `|`, che quindi non possono contenere); sotto, `.wi-input-list` con un radio `name="<campo>"` per opzione, che la lib spunta alla selezione.')
    ->note('bootstrap', 'Il combobox non esiste nel backend: il renderer estende quello del `Select` e aggiunge `data-wi-select-search="true"`, così il JS backend monta Select2 con il filtro.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            $regioni = [
                'lombardia' => 'Lombardia',
                'piemonte' => 'Piemonte',
                'veneto' => 'Veneto',
                'emilia-romagna' => 'Emilia-Romagna',
                'toscana' => 'Toscana',
                'lazio' => 'Lazio',
                'campania' => 'Campania',
                'sicilia' => 'Sicilia',
            ];

            (new TextList('region'))
                ->label('Regione')
                ->options($regioni)
            PHP)
            ->description('Le chiavi sono i valori inviati, le etichette quello che si legge e si cerca.')
            ->height(260)
    )
    ->example(
        Example::make('Con valore selezionato')
            ->code(<<<'PHP'
            (new TextList('province'))
                ->label('Provincia')
                ->options([
                    'MI' => 'Milano',
                    'BG' => 'Bergamo',
                    'BS' => 'Brescia',
                    'CO' => 'Como',
                    'VA' => 'Varese',
                ])
                ->value('BG')
                ->required()
            PHP)
            ->description('Il `value()` è la chiave dell\'opzione: nel frontend l\'input mostra «Bergamo» e il radio corrispondente parte già spuntato.')
            ->height(260)
    )
    ->example(
        Example::make('Dal DSL delle Resource')
            ->code(<<<'PHP'
            FormField::key('region')->textList([
                'lombardia' => 'Lombardia',
                'piemonte' => 'Piemonte',
                'veneto' => 'Veneto',
            ])->label('Regione')
            PHP)
            ->description('`textList()` ritorna `Inputs\InputTextList`; per le opzioni caricate via AJAX usa invece `searchText()` o `searchRadio()`.')
            ->height(260)
    );
