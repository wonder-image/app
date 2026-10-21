<?php

use Wonder\Auth\Frontend\AccountPagination;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Frontend\Pagination;

return ComponentDoc::for(Pagination::class)
    ->title('Pagination')
    ->group('choice')
    ->order(40)
    ->tags('pagine', 'paginazione', 'frontend', 'account')
    ->description('Precedente, la pagina corrente con due vicine per lato, successiva. Non conta righe e non legge la richiesta: `make($page, $pages)` riceve i conti già fatti, `url()` prende la base (la prima pagina è la base, le altre aggiungono `?page=N`) o una funzione `fn (int $page): string`. Con `summary()` l\'elenco va nel piede di una tabella a righe insieme al riepilogo. `Pagination::account($pagination, $baseUrl)` usa i conti di `AccountPagination::make()`, il link `?pagina=N` e i testi `account.pagination.*`.')
    ->uses(AccountPagination::class)
    ->docs('concetti/componenti/README.md#emptystate-e-pagination', 'Componenti UI')
    ->related('empty-state', 'steps')
    ->note('wonder', 'Usa `.wi-pagination`, `.wi-pagination__item` e, con il riepilogo, `.wi-row-table__foot` della lib. Le etichette di default sono `components.pagination.*`.')
    ->example('Generica', <<<'PHP'
    Pagination::make(4, 12)->url('/blog/')
    PHP)
    ->example('Con riepilogo e link costruiti', <<<'PHP'
    Pagination::make(2, 5)
        ->url(fn (int $page): string => '/blog/pagina/'.$page.'/')
        ->summary('Articoli da 11 a 20 di 47')
    PHP)
    ->example('Pannello account', <<<'PHP'
    Pagination::account(AccountPagination::make(42, 3), '/account/ordini/')
    PHP, 'Senza righe non stampa nulla.');
