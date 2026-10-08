<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\ButtonGroup;

return ComponentDoc::for(Badge::class)
    ->title('Badge')
    ->group('feedback')
    ->order(20)
    ->tags('etichetta', 'stato', 'contatore', 'pill')
    ->description('Un\'etichetta compatta per stati, categorie e contatori. Con un `href` diventa un link; `pill()` arrotonda i bordi e `outline()` toglie il riempimento. Le varianti hanno lo stesso nome nei due temi.')
    ->uses(ButtonGroup::class)
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->related('button', 'button-group')
    ->note('wonder', 'Le classi sono `badge-<variante>` e `badge-<variante>-o` per la versione outline, generate da `color.css` del sito.')
    ->example('Base', <<<'PHP'
    Badge::make('Bozza')
    PHP, 'Senza `variant()` il badge è `secondary`.')
    ->example('Varianti', <<<'PHP'
    ButtonGroup::make([
        Badge::make('Nuovo')->variant('primary'),
        Badge::make('Pubblicato')->variant('success'),
        Badge::make('In attesa')->variant('warning'),
        Badge::make('Scaduto')->variant('danger'),
        Badge::make('Archiviato')->variant('secondary')->outline(),
    ])
    PHP)
    ->example('Pill, icona e link', <<<'PHP'
    ButtonGroup::make([
        Badge::make('12')->variant('danger')->pill(),
        Badge::make('Verificato')->variant('success')->icon('bi bi-check2'),
        Badge::to('/backend/orders/', 'Ordini')->variant('primary')->pill()->icon('bi bi-box-arrow-up-right', 'end'),
    ])
    PHP, 'Con `to()` o `href()` il badge è un `<a>`; `icon()` accetta la posizione `start` o `end`.');
