# Charts

Le classi in `Wonder\Elements\Charts` permettono di creare grafici Chart.js direttamente dal codice PHP del progetto.

Al momento sono disponibili:

* `Wonder\Elements\Charts\LineChart`
* `Wonder\Elements\Charts\PieChart`

La stessa istanza viene renderizzata correttamente sia con tema `wonder` sia con tema `bootstrap`.

## Prerequisiti

Per usare questi elementi:

* Chart.js deve essere gia' caricato nella pagina;
* il tema attivo deve essere `wonder` oppure `bootstrap`;
* il grafico va stampato con `->render()`.

Nel progetto la libreria viene gia' importata durante l'installazione delle librerie, quindi questa documentazione copre solo l'uso delle classi PHP.

## Esempio rapido

```php
<?php

use Wonder\App\Theme;
use Wonder\Elements\Charts\LineChart;

Theme::set('wonder'); // oppure Theme::set('bootstrap');

echo LineChart::make()
    ->id('sales-chart')
    ->title('Vendite 2026')
    ->labels(['Gen', 'Feb', 'Mar', 'Apr'])
    ->series([120, 190, 170, 220], 'Vendite', [
        'borderColor' => '#0d6efd',
        'backgroundColor' => 'rgba(13, 110, 253, 0.15)',
        'pointBackgroundColor' => '#0d6efd',
    ])
    ->mergeOptions([
        'scales' => [
            'y' => [
                'beginAtZero' => true,
            ],
        ],
    ])
    ->render();
```

## Grafico lineare

`LineChart` crea un grafico di tipo `line`.

Esempio con due serie:

```php
<?php

use Wonder\Elements\Charts\LineChart;

echo LineChart::make()
    ->title('Andamento ordini')
    ->labels(['Gen', 'Feb', 'Mar', 'Apr'])
    ->series([32, 45, 41, 60], '2025', [
        'borderColor' => '#6c757d',
        'backgroundColor' => 'rgba(108, 117, 125, 0.12)',
    ])
    ->series([40, 52, 66, 81], '2026', [
        'borderColor' => '#198754',
        'backgroundColor' => 'rgba(25, 135, 84, 0.12)',
    ])
    ->mergeOptions([
        'plugins' => [
            'legend' => [
                'position' => 'bottom',
            ],
        ],
    ])
    ->render();
```

Di default `LineChart::series()` imposta:

* `fill => false`
* `tension => 0.35`

Se serve un comportamento diverso basta sovrascriverlo nel terzo parametro di `series()`.

## Grafico a torta

`PieChart` crea un grafico di tipo `pie`.

Esempio:

```php
<?php

use Wonder\Elements\Charts\PieChart;

echo PieChart::make()
    ->title('Origine traffico')
    ->labels(['Organico', 'ADV', 'Referral'])
    ->series([55, 30, 15], 'Canali', [
        'backgroundColor' => ['#198754', '#ffc107', '#0dcaf0'],
        'borderWidth' => 0,
    ])
    ->render();
```

Di default `PieChart` posiziona la legenda in basso.

## Metodi principali

| Metodo | Descrizione |
| --- | --- |
| `id(string $id)` | Imposta l'identificativo del grafico. Se omesso viene generato automaticamente. |
| `title(string $title)` | Aggiunge un titolo sopra al grafico nel renderer del tema. |
| `labels(array $labels)` | Imposta le etichette del grafico. |
| `series(array $data, ?string $label = null, array $dataset = [])` | Aggiunge rapidamente una serie per `LineChart` o `PieChart`. |
| `dataset(array $dataset)` | Aggiunge manualmente un dataset in formato Chart.js. |
| `datasets(array $datasets)` | Sostituisce tutti i dataset con un array di dataset Chart.js. |
| `width(int|string $width)` | Imposta la larghezza del contenitore del canvas. Se intero viene convertito in `px`. |
| `height(int|string $height)` | Imposta l'altezza del contenitore del canvas. Se intero viene convertito in `px`. |
| `legend(string $position = 'top')` | Mostra la legenda e ne imposta la posizione. |
| `hideLegend()` | Nasconde la legenda. |
| `responsive(bool $responsive = true)` | Imposta `options.responsive`. |
| `maintainAspectRatio(bool $maintainAspectRatio = true)` | Imposta `options.maintainAspectRatio`. |
| `options(array $options)` | Sostituisce completamente l'array `options` di Chart.js. |
| `mergeOptions(array $options)` | Unisce ricorsivamente le opzioni nuove a quelle gia' presenti. |
| `class(string $class)` | Imposta una o piu' classi CSS sul contenitore esterno. |
| `addClass(string $class)` | Aggiunge una classe CSS al contenitore esterno. |
| `attr(string $key, mixed $value)` | Aggiunge attributi HTML al contenitore esterno. |

## Usare i dataset nativi di Chart.js

Le classi non limitano la configurazione ai soli campi esposti dai metodi helper.

I dataset passati a `dataset()`, `datasets()` e al terzo parametro di `series()` vengono inoltrati a Chart.js cosi' come sono. Questo permette di usare anche opzioni native come:

* `borderColor`
* `backgroundColor`
* `pointRadius`
* `borderWidth`
* `fill`
* `tension`

Esempio:

```php
<?php

use Wonder\Elements\Charts\LineChart;

echo LineChart::make()
    ->labels(['Lun', 'Mar', 'Mer', 'Gio', 'Ven'])
    ->dataset([
        'label' => 'Visite',
        'data' => [120, 150, 130, 210, 260],
        'borderColor' => '#dc3545',
        'backgroundColor' => 'rgba(220, 53, 69, 0.15)',
        'pointRadius' => 4,
        'fill' => true,
        'tension' => 0.2,
    ])
    ->render();
```

## Usare le opzioni native di Chart.js

Per configurazioni semplici si puo' usare `legend()`, `hideLegend()`, `responsive()` e `maintainAspectRatio()`.

Per tutte le altre opzioni e' consigliato usare `mergeOptions()`, perche' mantiene anche le impostazioni gia' definite dal componente.

Esempio:

```php
<?php

use Wonder\Elements\Charts\LineChart;

echo LineChart::make()
    ->labels(['Gen', 'Feb', 'Mar'])
    ->series([10, 20, 15], 'Fatturato')
    ->mergeOptions([
        'plugins' => [
            'tooltip' => [
                'enabled' => true,
            ],
        ],
        'scales' => [
            'y' => [
                'beginAtZero' => true,
            ],
        ],
    ])
    ->render();
```

Usare `options()` solo quando si vuole sostituire l'intero blocco `options`.

## Dimensioni e classi CSS

Larghezza e altezza vengono applicate al contenitore del canvas:

```php
<?php

use Wonder\Elements\Charts\PieChart;

echo PieChart::make()
    ->class('my-chart-wrapper')
    ->width('100%')
    ->height(280)
    ->labels(['A', 'B', 'C'])
    ->series([30, 45, 25], 'Valori', [
        'backgroundColor' => ['#0d6efd', '#198754', '#ffc107'],
    ])
    ->render();
```

## Note operative

* Se non viene passato `id()`, il framework genera automaticamente un identificativo valido.
* Se un canvas con lo stesso id contiene gia' un'istanza Chart.js, il renderer la distrugge prima di crearne una nuova.
* Se Chart.js non e' disponibile nel browser, l'HTML viene renderizzato ma il grafico non viene inizializzato.
* Le classi e gli attributi aggiunti con `class()`, `addClass()` e `attr()` vengono applicati al contenitore esterno del componente.

## Classi disponibili

| Classe | Tipo Chart.js |
| --- | --- |
| `Wonder\Elements\Charts\LineChart` | `line` |
| `Wonder\Elements\Charts\PieChart` | `pie` |

Per aggiungere nuovi tipi di grafico bisogna creare un nuovo elemento in `Wonder\Elements\Charts` e il relativo renderer nei temi `Wonder` e `Bootstrap`.
