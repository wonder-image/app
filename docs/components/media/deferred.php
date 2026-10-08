<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Media\Deferred;
use Wonder\Elements\Media\Iframe;

return ComponentDoc::for(Deferred::class)
    ->title('Deferred')
    ->order(40)
    ->tags('differito', 'consenso', 'lazy', 'wrapper')
    ->description('Il wrapper generico `wi-deferred` che tiene un contenuto (un `Iframe` o HTML fidato) finché il visitatore non lo chiede. `mode()` sceglie il comportamento, `button()` il bottone, `fallbackUrl()` il link senza JavaScript, `fill()` lo fa riempire il genitore. JavaScript e CSS strutturale sono nella lib. È ciò che `Iframe::deferred()` usa sotto.')
    ->uses(Button::class, Iframe::class)
    ->docs('elementi/deferred-media.md', 'Iframe e contenuti differiti')
    ->related('iframe', 'video')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            Deferred::make(
                Iframe::url('https://www.openstreetmap.org/export/embed.html?bbox=11.23%2C43.76%2C11.27%2C43.79&layer=mapnik')
                    ->attr('title', 'Mappa di Firenze')
                    ->attr('style', 'width: 100%; height: 280px; border: 0;')
            )
                ->fallbackUrl('https://www.openstreetmap.org/#map=14/43.77/11.25')
            PHP)
            ->description('`fallbackUrl()` è il link che sostituisce il contenuto quando il JavaScript non c\'è.')
            ->height(300)
    )
    ->example(
        Example::make('Con un bottone personalizzato')
            ->code(<<<'PHP'
            Deferred::make(
                Iframe::url('https://www.openstreetmap.org/export/embed.html?bbox=14.22%2C40.83%2C14.29%2C40.87&layer=mapnik')
                    ->attr('title', 'Mappa di Napoli')
                    ->attr('style', 'width: 100%; height: 280px; border: 0;')
            )
                ->button(Button::make('Mostra la mappa')->variant('dark')->icon('bi bi-geo-alt', 'start'))
            PHP)
            ->height(300)
    );
