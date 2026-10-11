<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\GoogleReviews;

return ComponentDoc::for(GoogleReviews::class)
    ->title('GoogleReviews')
    ->group('content')
    ->order(60)
    ->tags('recensioni', 'google', 'voto', 'stelle', 'sedi')
    ->description('Le recensioni Google di una sede salvate dal task `wonder.google-places`. `fromLocation($location)` legge `$location->reviews` e il voto medio da `$location->google`. Le attribuzioni richieste da Google non si spengono: avatar, nome e profilo dell\'autore, link alla recensione su Google Maps, avviso sull\'ordine (`notice()` lo cambia) e la scritta «Google Maps» con `translate="no"`. Una recensione tradotta mostra l\'originale in un `<details>`. Senza recensioni e senza voto non stampa nulla.')
    ->docs('concetti/sedi.md#recensioni-google', 'Sedi')
    ->related('card')
    ->note('wonder', 'Usa `d-flex`, `d-grid`, `gap-*` e `text-small` della lib; allineamento, avatar, stelle e attribuzione stanno in un `<style>` stampato una volta per pagina.')
    ->note('bootstrap', 'Usa le utility di Bootstrap (`d-flex`, `small`, `text-body-secondary`, `border-top`) e lo stesso `<style>` condiviso.')
    ->example('Da una sede', <<<'PHP'
    GoogleReviews::fromLocation([
        'google' => ['rating' => 4.7, 'rating_count' => 128, 'maps_uri' => 'https://maps.google.com/?cid=1'],
        'reviews' => [
            [
                'rating' => 5,
                'text' => "Personale gentile e preparato.\nTornerò sicuramente.",
                'language' => 'it',
                'author_name' => 'Giulia R.',
                'author_uri' => 'https://www.google.com/maps/contrib/1',
                'publish_time' => '2026-10-01 10:00:00',
                'relative_time' => '2 settimane fa',
                'maps_uri' => 'https://www.google.com/maps/reviews/1',
            ],
            [
                'rating' => 4,
                'text' => 'Ottimo servizio, un po\' di attesa.',
                'language' => 'it',
                'original_text' => 'Great service, a bit of a wait.',
                'original_language' => 'en',
                'author_name' => 'John D.',
                'author_uri' => 'https://www.google.com/maps/contrib/2',
                'publish_time' => '2026-09-12 18:30:00',
                'relative_time' => 'un mese fa',
                'maps_uri' => 'https://www.google.com/maps/reviews/2',
            ],
        ],
    ])
    PHP, 'Nel sito: `GoogleReviews::fromLocation(SocietyLocations::default())`.')
    ->example('Solo il voto medio, con un altro avviso', <<<'PHP'
    GoogleReviews::make()
        ->summary(4.2, 37, 'https://maps.google.com/?cid=1')
        ->notice('Voto medio su Google.')
    PHP)
    ->example('Al massimo una recensione', <<<'PHP'
    GoogleReviews::make([
        ['rating' => 5, 'text' => 'Consigliato.', 'author_name' => 'Marco', 'publish_time' => '2026-10-05 09:00:00'],
        ['rating' => 3, 'text' => 'Nella media.', 'author_name' => 'Sara', 'publish_time' => '2026-08-20 12:00:00'],
    ])->limit(1)
    PHP, 'Un URL che non è `https://` viene scartato; una recensione senza nome né avatar non si mostra.');
