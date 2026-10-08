<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\MetricCard;

return ComponentDoc::for(MetricCard::class)
    ->title('MetricCard')
    ->group('content')
    ->order(50)
    ->tags('kpi', 'metrica', 'statistica', 'delta')
    ->description('Una card per un KPI: valore, unità e confronto opzionale con un valore precedente. `compareTo()` calcola il delta sul numero originale, `displayValue()` ne mostra una versione formattata, `lowerIsBetter()` inverte il colore senza cambiare la freccia. Sostituisce `wiCardStats()`.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md#infocard-e-metriccard', 'Componenti UI')
    ->related('info-card', 'data-item')
    ->example('Base', <<<'PHP'
    MetricCard::make('Fatturato', 120)->unit(' EUR')
    PHP)
    ->example('Con confronto', <<<'PHP'
    (new Container())->columns(3)->gap(3)->components([
        MetricCard::make('Ordini', 148)->compareTo(120),
        MetricCard::make('Churn', 4.2)->displayValue('4,2')->unit('%')->compareTo(5.1)->lowerIsBetter()->deltaPrecision(1),
        MetricCard::make('Resi', 12)->compareTo(0),
    ])
    PHP, 'Una baseline pari a zero non divide per zero: il delta resta `--`.');
