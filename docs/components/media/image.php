<?php

use Wonder\Docs\Assets;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Media\Image;

return ComponentDoc::for(Image::class)
    ->title('Image')
    ->order(10)
    ->tags('immagine', 'responsive', 'webp', 'lazy', 'picture')
    ->description('Un\'immagine responsive. Per i file raster locali il renderer genera il `<picture>` con le varianti di larghezza (`RESPONSIVE_IMAGE_SIZES`) e il WebP; `displaySizes()` scrive l\'attributo `sizes`, `loading()` e `priority()` governano il caricamento, `skeleton()` mostra il segnaposto finché l\'immagine non arriva. Le immagini del catalogo sono SVG: niente varianti, il tag resta un `<img>`.')
    ->uses(Assets::class, Container::class)
    ->docs('elementi/responsive-media.md', 'Immagini di Swiper e Gallery')
    ->docs('concetti/frontend-performance.md', 'Performance frontend')
    ->related('gallery', 'swiper', 'video')
    ->note('wonder', 'Con `columnSpan()` il wrapper usa `col-*`, `col-t-*`, `col-p-*` della lib; senza, l\'output è il solo tag.')
    ->note('bootstrap', 'Con `columnSpan()` il wrapper è `col-span-*`; senza, l\'output è il solo tag.')
    ->example('Base', <<<'PHP'
    Image::src(Assets::url('paesaggio-1.svg'))
        ->alt('Un paesaggio di esempio')
        ->addClass('img-fluid rounded')
    PHP)
    ->example('Caricamento e priorità', <<<'PHP'
    (new Container())->columns(2)->gap(3)->components([
        Image::src(Assets::url('paesaggio-2.svg'))->alt('In alto nella pagina')->priority()->addClass('img-fluid'),
        Image::src(Assets::url('paesaggio-3.svg'))->alt('Sotto la piega')->loading('lazy')->skeleton()->notDraggable()->addClass('img-fluid'),
    ])
    PHP, '`priority()` toglie il lazy loading e alza la priorità di fetch per l\'immagine LCP; `skeleton()` aggiunge il segnaposto animato della lib.')
    ->example('Dimensioni e sizes', <<<'PHP'
    Image::src(Assets::url('quadrato-1.svg'))
        ->alt('Quadrato')
        ->size(480)
        ->displaySizes('(min-width: 992px) 33vw, 100vw')
        ->addClass('img-fluid')
    PHP, '`size()` limita la variante più grande, `sizes()` elenca quelle da generare, `displaySizes()` dice al browser quanto spazio occupa l\'immagine.');
