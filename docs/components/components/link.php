<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Link;

return ComponentDoc::for(Link::class)
    ->title('Link')
    ->group('action')
    ->order(40)
    ->tags('collegamento', 'anchor', 'href')
    ->description('Un link testuale, con icona e versione attenuata. È la base di `Button` e `Badge`: gli attributi `href`, `target`, `rel`, `title`, `onclick` e `download` vengono dal concern condiviso `HasLinkAttributes`.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->related('button', 'badge', 'text')
    ->example('Base', <<<'PHP'
    Link::to('/backend/app/scheduler/', 'Vai allo scheduler')
    PHP)
    ->example('Icona, nuova scheda e attenuato', <<<'PHP'
    (new Container())->gap(2)->components([
        Link::to('https://www.wonderimage.it', 'Sito Wonder Image')->blank()->icon('bi bi-box-arrow-up-right', 'end'),
        Link::to('/download/report.pdf', 'Scarica il report')->download()->icon('bi bi-download'),
        Link::to('/backend/app/log/', 'Registro completo')->muted(),
    ])
    PHP, '`blank()` aggiunge `target="_blank"` e `rel="noopener noreferrer"`; `download()` scrive l\'attributo nativo.');
