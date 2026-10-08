<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Charts\Dataset;
use Wonder\Elements\Charts\LineChart;

return ComponentDoc::for(LineChart::class)
    ->title('LineChart')
    ->order(20)
    ->tags('grafico', 'linee', 'chart.js', 'andamento')
    ->description('Un grafico a linee Chart.js per gli andamenti nel tempo. Ogni `series()` è una linea; con un `Dataset` si impostano colore, riempimento (`fill()`), curvatura (`tension()`) e punti. `mergeOptions()`, `xAxis()` e `yAxis()` ritoccano la configurazione senza riscriverla.')
    ->uses(Dataset::class)
    ->docs('concetti/componenti/charts.md', 'Charts')
    ->related('bar-chart', 'pie-chart')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            LineChart::make()
                ->title('Vendite 2026')
                ->labels(['Gen', 'Feb', 'Mar', 'Apr', 'Mag', 'Giu'])
                ->series([120, 190, 170, 220, 210, 260], 'Vendite', [
                    'borderColor' => '#0d6efd',
                    'backgroundColor' => 'rgba(13, 110, 253, 0.15)',
                ])
                ->yAxis(['beginAtZero' => true])
                ->height(260)
            PHP)
            ->height(300)
    )
    ->example(
        Example::make('Due serie con area e curve')
            ->code(<<<'PHP'
            LineChart::make()
                ->labels(['Lun', 'Mar', 'Mer', 'Gio', 'Ven', 'Sab', 'Dom'])
                ->series([30, 45, 40, 60, 55, 80, 70], 'Visite', Dataset::make()->borderColor('#6f42c1')->backgroundColor('rgba(111, 66, 193, 0.15)')->fill()->tension(0.35))
                ->series([5, 8, 6, 12, 9, 15, 11], 'Ordini', Dataset::make()->borderColor('#20c997')->tension(0.35)->pointRadius(4))
                ->legend('bottom')
                ->height(260)
            PHP)
            ->height(300)
    );
