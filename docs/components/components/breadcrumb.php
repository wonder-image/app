<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Breadcrumb;

return ComponentDoc::for(Breadcrumb::class)
    ->title('Breadcrumb')
    ->group('action')
    ->order(45)
    ->tags('breadcrumb', 'percorso', 'navigazione', 'briciole')
    ->description('Il percorso di navigazione della pagina. `make()` accetta una mappa `URL => nome` oppure una lista di `[\'url\' => ..., \'name\' => ...]`; l\'ultima voce è la pagina corrente, senza link e con `aria-current="page"`. Gli URL locali passano da `__u()`, quelli assoluti restano come sono. `label()` cambia l\'`aria-label` del `<nav>`.

Il componente non genera JSON-LD: i dati strutturati del breadcrumb vanno in `$SEO->breadcrumb`, che accetta la stessa mappa.')
    ->docs('concetti/head-e-breadcrumb.md', 'Head, schema.org e breadcrumb')
    ->related('steps', 'link')
    ->note('wonder', 'Una lista in linea con il separatore `/` nascosto alle tecnologie assistive.')
    ->note('bootstrap', 'Rende `breadcrumb`, `breadcrumb-item` e `active` di Bootstrap.')
    ->example('Mappa URL => nome', <<<'PHP'
    Breadcrumb::make([
        '/' => 'Home',
        '/catalogo/' => 'Catalogo',
        '/catalogo/badge/' => 'Badge',
    ])
    PHP, 'L\'URL dell\'ultima voce non viene stampato: è la pagina in cui ci si trova.')
    ->example('Lista esplicita ed etichetta', <<<'PHP'
    Breadcrumb::make([
        ['url' => 'https://example.com/', 'name' => 'Sito esterno'],
        ['url' => '/account/', 'name' => 'Account'],
        ['name' => 'Ordini'],
    ])
        ->label('Percorso')
        ->class('mb-0')
    PHP, 'Un URL assoluto resta invariato; la stessa lista si può passare a `$SEO->breadcrumb` per il JSON-LD.');
