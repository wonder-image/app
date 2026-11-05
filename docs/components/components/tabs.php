<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Tabs;

return ComponentDoc::for(Tabs::class)
    ->title('Tabs')
    ->group('choice')
    ->order(40)
    ->tags('schede', 'tab', 'pannelli', 'nav-tabs')
    ->description('Schede con un pannello ciascuna. `tab($key, $label, $components)` aggiunge una scheda nell\'ordine di chiamata, `active($key)` apre quella chiesta (di default la prima). Con una sola scheda esce solo il pannello, senza barra. L\'etichetta si escapa.')
    ->docs('concetti/componenti/README.md#tabs', 'Componenti UI')
    ->related('modal')
    ->uses(FormField::class)
    ->note('bootstrap', 'Rende `nav-tabs` e `tab-content` di Bootstrap.')
    ->example('Due schede', <<<'PHP'
    Tabs::make('file')
        ->columns(12)
        ->tab('export', 'Esporta', [FormField::key('format')->text()->columnSpan(6)])
        ->tab('import', 'Importa', [FormField::key('file')->text()->columnSpan(6)])
    PHP)
    ->example('Scheda aperta', <<<'PHP'
    Tabs::make('file')
        ->tab('export', 'Esporta', [])
        ->tab('import', 'Importa', [])
        ->active('import')
    PHP, 'Senza `active()` si apre la prima scheda.');
