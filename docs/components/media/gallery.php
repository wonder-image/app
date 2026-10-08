<?php

use Wonder\Docs\Assets;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Media\Gallery;

return ComponentDoc::for(Gallery::class)
    ->title('Gallery')
    ->order(50)
    ->tags('galleria', 'immagini', 'lightbox', 'griglia', 'fancybox')
    ->description('Una griglia di immagini con lightbox (Fancyapps). `columns()` dà le colonne per desktop, tablet e telefono, `gap()` lo spazio, `format()` il taglio delle miniature, `download()` il bottone di scaricamento nel lightbox; `size()` e `fullSize()` scelgono la larghezza delle varianti per la miniatura e per l\'immagine aperta. Sostituisce la vecchia `responsiveGallery()`.')
    ->uses(Assets::class)
    ->docs('concetti/componenti/swiper-e-gallery.md', 'Swiper e Gallery')
    ->docs('elementi/responsive-media.md', 'Immagini di Swiper e Gallery')
    ->related('swiper', 'image')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            Gallery::make([
                Assets::url('paesaggio-1.jpg'),
                Assets::url('paesaggio-2.jpg'),
                Assets::url('paesaggio-3.jpg'),
                Assets::url('paesaggio-2.jpg'),
                Assets::url('paesaggio-3.jpg'),
                Assets::url('paesaggio-1.jpg'),
            ])
            PHP)
            ->description('Di default 4 colonne su desktop, 3 su tablet e 2 su telefono; ogni immagine apre il lightbox.')
            ->height(360)
    )
    ->example(
        Example::make('Colonne, spazio e download')
            ->code(<<<'PHP'
            Gallery::make([
                Assets::url('paesaggio-1.jpg'),
                Assets::url('paesaggio-2.jpg'),
                Assets::url('paesaggio-1.jpg'),
            ])
                ->columns(3, 2, 1)
                ->gap(3)
                ->format('h-fit')
                ->download()
                ->imageSizes('(min-width: 992px) 33vw, 100vw')
            PHP)
            ->height(420)
    );
