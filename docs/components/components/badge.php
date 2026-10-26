<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Badge;
use Wonder\Elements\Components\ButtonGroup;

return ComponentDoc::for(Badge::class)
    ->title('Badge')
    ->group('feedback')
    ->order(20)
    ->tags('etichetta', 'stato', 'contatore', 'pill', 'inline')
    ->description('Un\'etichetta compatta per stati, categorie e contatori. Con un `href` diventa un link; `pill()` arrotonda i bordi e `outline()` toglie il riempimento. Le varianti hanno lo stesso nome nei due temi. Con `inline()` esce solo il `<span>` (o `<a>`), senza la colonna: per metterlo accanto ad altro testo.')
    ->uses(ButtonGroup::class)
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->related('button', 'button-group')
    ->note('bootstrap', 'Senza `inline()` il badge è avvolto in `<div class="col-span-*">` (vedi `columnSpan()`); con `inline()` il `<div>` sparisce e `columnSpan()` è ignorato.')
    ->note('wonder', 'Le classi sono `badge-<variante>` e `badge-<variante>-o` per la versione outline, generate da `color.css` del sito. Il badge è già in linea: `inline()` non cambia l\'HTML.')
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
    PHP, 'Con `to()` o `href()` il badge è un `<a>`; `icon()` accetta la posizione `start` o `end`.')
    ->example('In linea', <<<'PHP'
    [
        'Ordine #1042 ',
        Badge::make('Pagato')->variant('success')->inline(),
        ' ',
        Badge::make('deprecato')->variant('warning')->title('Usa la nuova API')->addClass('ms-1')->inline(),
    ]
    PHP, 'Con `inline()` il badge si concatena a testo o a una cella di tabella (`->render(\'bootstrap\')` nelle Resource del backend). Classi extra con `addClass()`, tooltip con `title()` o `attr()`; la label è escapata dal renderer, quindi va passata non escapata.');
