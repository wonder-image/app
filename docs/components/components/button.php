<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;

return ComponentDoc::for(Button::class)
    ->title('Button')
    ->group('action')
    ->order(10)
    ->tags('bottone', 'cta', 'link', 'post', 'conferma')
    ->description('Un bottone o una call to action. `make()` rende un `<button>`, con un `href` diventa un link; `post()` incarta il bottone in un `<form method="post">` con token CSRF, e `confirm()` chiede conferma con il dialogo della lib prima di procedere.')
    ->uses(ButtonGroup::class)
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->docs('elementi/button-lightbox.md', 'Button con lightbox')
    ->related('badge', 'button-group', 'dropdown', 'link')
    ->note('bootstrap', 'Nel backend il bottone esce dentro una colonna `col-span-*` della griglia; fuori da un layout usa `->inline()` tramite attributi se serve il solo tag.')
    ->note('wonder', 'Le classi seguono la lib: `btn-<variante>` e `btn-<variante>-o` per la versione outline.')
    ->example('Base', <<<'PHP'
    Button::make('Salva')
    PHP, 'Il bottone più semplice: variante `primary` e `type="button"`.')
    ->example('Varianti', <<<'PHP'
    ButtonGroup::make([
        Button::make('Primario')->variant('primary'),
        Button::make('Secondario')->variant('secondary'),
        Button::make('Successo')->variant('success'),
        Button::make('Pericolo')->variant('danger'),
        Button::make('Outline')->variant('primary')->outline(),
    ])
    PHP, 'Il nome della variante è lo stesso nei due temi; `outline()` toglie il riempimento.')
    ->example('Con icona e dimensioni', <<<'PHP'
    ButtonGroup::make([
        Button::make('Piccolo')->size('sm')->icon('bi bi-check2', 'start'),
        Button::make('Normale')->icon('bi bi-check2', 'start'),
        Button::make('Grande')->size('lg')->icon('bi bi-arrow-right', 'end'),
    ])
    PHP)
    ->example('Link e stati', <<<'PHP'
    ButtonGroup::make([
        Button::to('https://www.wonderimage.it', 'Apri il sito')->blank()->icon('bi bi-box-arrow-up-right'),
        Button::make('Attivo')->active(),
        Button::make('Disabilitato')->disabled(),
    ])
    PHP, 'Con un `href` il bottone è un `<a>`; `blank()` aggiunge `target="_blank"` e `rel="noopener noreferrer"`. Un bottone disabilitato resta un `<button>` anche con un href.')
    ->example(
        Example::make('Azione POST con conferma')
            ->code(<<<'PHP'
            Button::post('/backend/publish/', 'Pubblica')
                ->variant('success')
                ->icon('bi bi-cloud-arrow-up', 'start')
                ->hidden(['id' => 12])
                ->confirm('Pubblicare ora?', title: 'Pubblicazione', ok: 'Pubblica', variant: 'success')
            PHP)
            ->description('`post()` genera un `<form method="post">` con il token CSRF e un `<input type="hidden">` per ogni campo di `hidden()`; la conferma usa gli attributi `data-wi-confirm*` letti dalla lib.')
            ->height(96)
    )
    ->example('A tutta larghezza con freccia', <<<'PHP'
    Button::make('Continua')->block()->arrow()
    PHP, '`arrow()` aggiunge l\'icona `bi-chevron-right` in coda se non ce n\'è già una; nel tema Wonder mette anche la classe `btn-arrow`.');
