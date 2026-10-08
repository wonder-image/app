<?php

use Wonder\Docs\Assets;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Media\Swiper;

return ComponentDoc::for(Swiper::class)
    ->title('Swiper')
    ->order(60)
    ->tags('carosello', 'slider', 'swiper', 'miniature', 'zoom')
    ->description('Un carosello Swiper.js con due modalità, l\'ultima chiamata vince: `images()` tiene la pipeline immagini responsive con miniature, zoom e lightbox; `slides()` accetta HTML fidato o componenti e si occupa dei wrapper `.swiper-slide`. Le opzioni responsive (`breakpoints()`, `autoHeight()`, `keyboard()`, `watchOverflow()`) e i rapporti (`ratio()`, `thumbsRatio()`) stanno sul componente, non in script del sito.')
    ->uses(Assets::class, Badge::class, Button::class)
    ->docs('concetti/componenti/swiper-e-gallery.md', 'Swiper e Gallery')
    ->docs('elementi/responsive-media.md', 'Immagini di Swiper e Gallery')
    ->related('gallery', 'image')
    ->note('bootstrap', 'Senza un `ratio()` esplicito le slide immagine tengono il wrapper 16:9 storico del backend.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            Swiper::make([
                Assets::url('paesaggio-1.svg'),
                Assets::url('paesaggio-2.svg'),
                Assets::url('paesaggio-3.svg'),
            ])
                ->navigation()
                ->pagination()
                ->loop()
            PHP)
            ->height(380)
    )
    ->example(
        Example::make('Più slide, miniature e lightbox')
            ->code(<<<'PHP'
            Swiper::make([
                Assets::url('paesaggio-1.svg'),
                Assets::url('paesaggio-2.svg'),
                Assets::url('paesaggio-3.svg'),
                Assets::url('quadrato-1.svg'),
                Assets::url('quadrato-2.svg'),
            ])
                ->slidesPerView(2.2)
                ->spaceBetween(16)
                ->ratio('4:3')
                ->thumbnails()
                ->thumbsPerView(5)
                ->lightbox('catalogo')
                ->breakpoints([768 => ['slidesPerView' => 3.2]])
            PHP)
            ->description('`ratio()` vale per ogni slide, non per la radice del carosello: le slide multiple restano affiancate. `lightbox()` raggruppa le immagini in Fancyapps.')
            ->height(460)
    )
    ->example(
        Example::make('Slide di componenti')
            ->code(<<<'PHP'
            Swiper::make()
                ->slides([
                    Badge::make('Prima slide')->variant('primary'),
                    Button::make('Seconda slide')->variant('success'),
                    '<div class="p-4 border rounded">Terza slide, HTML fidato</div>',
                ])
                ->slidesPerView(1)
                ->navigation()
                ->autoHeight()
            PHP)
            ->description('Con `slides()` le opzioni solo-immagine (miniature, zoom, lightbox, download) vengono ignorate; i componenti si rendono con il tema del carosello.')
            ->height(200)
    );
