<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Charts\PieChart;

return ComponentDoc::for(PieChart::class)
    ->title('PieChart')
    ->order(30)
    ->tags('grafico', 'torta', 'chart.js', 'quote')
    ->description('Un grafico a torta Chart.js per le quote di un totale: `labels()` sono le fette, `series()` i valori con i colori di sfondo. `legend()` sceglie dove sta la legenda, `hideLegend()` la toglie.')
    ->docs('concetti/componenti/charts.md', 'Charts')
    ->related('bar-chart', 'line-chart')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            PieChart::make()
                ->title('Canali di vendita')
                ->labels(['Online', 'Negozio', 'Rivenditori'])
                ->series([55, 30, 15], 'Quota', [
                    'backgroundColor' => ['#0d6efd', '#20c997', '#ffc107'],
                ])
                ->legend('right')
                ->height(260)
            PHP)
            ->height(300)
    )
    ->example(
        Example::make('Senza legenda')
            ->code(<<<'PHP'
            PieChart::make()
                ->labels(['Sì', 'No'])
                ->series([72, 28], 'Risposte', ['backgroundColor' => ['#198754', '#dc3545']])
                ->hideLegend()
                ->width(260)
                ->height(260)
            PHP)
            ->height(300)
    );
