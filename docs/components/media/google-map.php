<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Media\GoogleMap;

return ComponentDoc::for(GoogleMap::class)
    ->title('GoogleMap')
    ->order(70)
    ->tags('mappa', 'google maps', 'marker', 'percorso', 'navigazione')
    ->description('Una mappa Google con marker, centro, zoom, percorso e navigazione, dichiarata in PHP: è il confine verso `requireGoogleMaps()`, `MapManager` e `MapNavigator` della lib. I dati dei marker restano senza HTML, il JSON è escapato, ogni mappa ha un\'altezza esplicita. La chiave API viene da `Credentials::api()` del sito o da `apiKey()`: nel catalogo non c\'è, quindi l\'anteprima mostra il contenitore senza le tessere.')
    ->docs('servizi/google-maps.md', 'Google Maps')
    ->related('iframe')
    ->note('wonder', 'Il contenitore e lo script sono gli stessi dei due temi; cambia solo la cornice.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            GoogleMap::make()
                ->center(45.4642, 9.19)
                ->zoom(13)
                ->height(320)
                ->marker(45.4642, 9.19, 'Milano', ['description' => 'Piazza del Duomo'])
            PHP)
            ->height(340)
    )
    ->example(
        Example::make('Più marker con percorso')
            ->code(<<<'PHP'
            GoogleMap::make([
                ['lat' => 45.4642, 'lng' => 9.19, 'title' => 'Duomo'],
                ['lat' => 45.4654, 'lng' => 9.1865, 'title' => 'Castello Sforzesco'],
                ['lat' => 45.4779, 'lng' => 9.1875, 'title' => 'Arco della Pace'],
            ])
                ->fitBounds()
                ->highlightMarkers()
                ->route([
                    ['lat' => 45.4642, 'lng' => 9.19],
                    ['lat' => 45.4654, 'lng' => 9.1865],
                    ['lat' => 45.4779, 'lng' => 9.1875],
                ])
                ->travelMode('WALKING')
                ->height(360)
            PHP)
            ->description('`route()` vuole almeno due punti; `navigation()` non avvia la geolocalizzazione se non lo chiedi esplicitamente.')
            ->height(380)
    );
