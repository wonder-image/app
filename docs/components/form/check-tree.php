<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\CheckTree;

return ComponentDoc::for(CheckTree::class)
    ->title('CheckTree')
    ->group('choice')
    ->order(70)
    ->tags('albero', 'jstree', 'categorie', 'gerarchia', 'checkbox')
    ->description('La variante ad albero di `CheckGroup`: le opzioni con `child` diventano una struttura `<ul><li>` che jsTree rende con spunte e rami. I nodi portano solo etichette escapate; i valori spuntati stanno in input nascosti nel contenitore `data-wi-tree-values`, sincronizzati dalla lib. `primaryField()` segna con una stella la voce principale fra quelle scelte. Nelle Resource: `FormField::key(\'nome\')->checkTree([...])`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md', 'FormField')
    ->related('check-group', 'dynamic-check')
    ->note('bootstrap', 'Serve jsTree, già nel set del backend; `searchBar()` aggiunge il filtro sopra l\'albero.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new CheckTree('categories'))
                ->label('Categorie')
                ->options([
                    1 => ['name' => 'Abbigliamento', 'child' => [
                        2 => 'Uomo',
                        3 => 'Donna',
                        4 => ['name' => 'Bambino', 'child' => [5 => 'Neonato', 6 => '3-10 anni']],
                    ]],
                    7 => ['name' => 'Scarpe', 'child' => [8 => 'Sneaker', 9 => 'Stivali']],
                    10 => 'Accessori',
                ])
                ->value([3, 8])
            PHP)
            ->height(320)
    )
    ->example(
        Example::make('Radio con voce principale e ricerca')
            ->code(<<<'PHP'
            (new CheckTree('main_category'))
                ->label('Categoria principale')
                ->inputType('radio')
                ->searchBar()
                ->primaryField('primary_category')
                ->options([
                    1 => ['name' => 'Casa', 'child' => [2 => 'Cucina', 3 => 'Bagno']],
                    4 => ['name' => 'Giardino', 'child' => [5 => 'Attrezzi', 6 => 'Piante']],
                ])
            PHP)
            ->height(320)
    )
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('categories')->checkTree([1 => ['name' => 'Radice', 'child' => [2 => 'Foglia']]], true)->label('Categorie')
    PHP);
