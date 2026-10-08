<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Charts\BarChart;
use Wonder\Elements\Charts\Dataset;

return ComponentDoc::for(BarChart::class)
    ->title('BarChart')
    ->order(10)
    ->tags('grafico', 'barre', 'chart.js', 'istogramma')
    ->description('Un grafico a barre Chart.js. `labels()` sono le categorie, ogni `series()` una serie con etichetta e opzioni (o un `Dataset`); `horizontal()` gira le barre, `stacked()` le impila. Il renderer carica Chart.js da solo e registra il grafico in `window.WonderCharts`.')
    ->uses(Dataset::class)
    ->docs('concetti/componenti/charts.md', 'Charts')
    ->related('line-chart', 'pie-chart')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            BarChart::make()
                ->title('Ordini per mese')
                ->labels(['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu'])
                ->series([12, 19, 8, 15, 22, 17], 'Ordini')
                ->height(260)
            PHP)
            ->height(300)
    )
    ->example(
        Example::make('Due serie impilate, orizzontali')
            ->code(<<<'PHP'
            BarChart::make()
                ->labels(['Nord', 'Centro', 'Sud'])
                ->series([120, 90, 60], 'Online', Dataset::make()->backgroundColor('#0d6efd'))
                ->series([40, 70, 85], 'Negozio', Dataset::make()->backgroundColor('#20c997'))
                ->horizontal()
                ->stacked()
                ->legend('bottom')
                ->height(240)
            PHP)
            ->description('`Dataset` è il modo tipizzato di passare le opzioni di Chart.js; un array con le stesse chiavi va bene lo stesso.')
            ->height(280)
    );
