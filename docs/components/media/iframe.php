<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Media\Iframe;

return ComponentDoc::for(Iframe::class)
    ->title('Iframe')
    ->order(30)
    ->tags('iframe', 'embed', 'mappa', 'youtube', 'differito')
    ->description('Un contenuto incorporato (mappa, video esterno, pagina). `fitCover()`/`fitContain()` governano l\'object fit in modo coerente fra i temi; `deferred()` rinvia il caricamento finché il visitatore non lo chiede, con un bottone personalizzabile da `deferredButton()`. L\'URL degli esempi è pubblico: senza rete l\'anteprima resta vuota.')
    ->uses(Container::class)
    ->docs('concetti/componenti/video-e-iframe.md', 'Video e Iframe')
    ->docs('elementi/deferred-media.md', 'Iframe e contenuti differiti')
    ->related('deferred', 'video', 'google-map')
    ->note('bootstrap', 'Per mantenere le proporzioni usa un `Container::noGrid()` con `ratio ratio-16x9` attorno all\'iframe, senza `columnSpan()` sull\'iframe.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            Iframe::url('https://www.openstreetmap.org/export/embed.html?bbox=9.17%2C45.46%2C9.21%2C45.48&layer=mapnik')
                ->attr('title', 'Mappa di Milano')
                ->attr('style', 'width: 100%; height: 320px; border: 0;')
            PHP)
            ->height(340)
    )
    ->example(
        Example::make('Con proporzioni fisse')
            ->code(<<<'PHP'
            (new Container())
                ->noGrid()
                ->addClass('ratio ratio-16x9 rounded overflow-hidden')
                ->components([
                    Iframe::url('https://www.openstreetmap.org/export/embed.html?bbox=12.47%2C41.88%2C12.51%2C41.91&layer=mapnik')
                        ->fitCover()
                        ->attr('title', 'Mappa di Roma')
                        ->attr('allowfullscreen', true),
                ])
            PHP)
            ->description('Il `ratio` di Bootstrap vuole stare sul genitore diretto: il `Container` con `noGrid()` è quel genitore.')
            ->themes('bootstrap')
            ->height(360)
    )
    ->example(
        Example::make('Differito')
            ->code(<<<'PHP'
            Iframe::url('https://www.openstreetmap.org/export/embed.html?bbox=9.17%2C45.46%2C9.21%2C45.48&layer=mapnik')
                ->deferred()
                ->attr('title', 'Mappa differita')
                ->attr('style', 'width: 100%; height: 280px; border: 0;')
            PHP)
            ->description('L\'iframe non parte finché il visitatore non preme «Carica contenuto»: niente richieste di terze parti prima del consenso.')
            ->height(300)
    );
