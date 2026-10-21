<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Link;
use Wonder\Elements\Components\Text;

return ComponentDoc::for(Text::class)
    ->title('Text')
    ->group('content')
    ->order(10)
    ->tags('testo', 'paragrafo', 'tipografia')
    ->description('Un testo escapato con le utility tipografiche del tema: `bold()`, `small()`, `muted()`, `italic()`, `color()`, `align()`, `lead()`. `tag()` cambia l\'elemento (`p`, `div`, `span`); `link()` e `append()` compongono un paragrafo con link inline; `html()` accetta markup fidato.')
    ->note('wonder', 'Usa le utility della lib: `fw-700`, `text-small`, `tx-secondary` per `muted()`, `tx-<colore>`, `a-l` / `a-c` / `a-r` per `align()`, `subtitle` per `lead()`. Il renderer ignora `italic()` e `align(\'justify\')` (Bootstrap stampa `fst-italic` e `text-justify`): la lib non ha queste utility. `color(\'x\')` stampa sempre `tx-x`, che esiste solo se `x` è un ruolo della palette del sito (`color.css` generato dai token): per esempio `danger` può non avere stile. Senza classi né `columnSpan()` stampa il solo tag, senza `<div>` intorno.')
    ->note('bootstrap', 'Il testo è sempre avvolto nel `<div>` di colonna; le classi sono quelle di Bootstrap (`fw-bold`, `small`, `text-body-secondary`, `fst-italic`, `text-<allineamento>`, `lead`).')
    ->uses(Container::class, Link::class)
    ->docs('concetti/componenti/README.md', 'Componenti UI')
    ->related('rich-text', 'help-text', 'link')
    ->example('Base', <<<'PHP'
    Text::make('Un paragrafo di testo semplice, escapato: <b>niente HTML</b>.')
    PHP)
    ->example('Stili', <<<'PHP'
    (new Container())->gap(1)->components([
        Text::make('In grassetto')->bold(),
        Text::make('Piccolo e attenuato')->small()->muted(),
        Text::make('Corsivo, allineato a destra')->italic()->align('end'),
        Text::make('Colorato')->color('danger'),
        Text::make('Un testo introduttivo in evidenza.')->lead(),
    ])
    PHP)
    ->example('Con link inline', <<<'PHP'
    Text::make('Per i dettagli vedi ')
        ->link('/backend/app/scheduler/', 'lo scheduler', ['icon' => 'bi bi-clock'])
        ->append(' oppure ')
        ->append(Link::to('/backend/app/log/', 'i log')->muted())
        ->append('.')
        ->tag('div')
    PHP, '`link()` usa le stesse opzioni del concern link condiviso (`blank`, `title`, `class`, `muted`, `attributes`).');
