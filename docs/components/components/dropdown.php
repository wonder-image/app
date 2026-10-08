<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Dropdown;

return ComponentDoc::for(Dropdown::class)
    ->title('Dropdown')
    ->group('action')
    ->order(30)
    ->tags('menu', 'azioni', 'voci', 'post', 'conferma')
    ->description('Un bottone che apre un menu di voci: link (`item()`), azioni JavaScript (`action()`), bottoni (`button()`), intestazioni, testi e separatori. Una voce con `\'method\' => \'post\'` diventa un form con token CSRF; `confirm` chiede conferma con il dialogo della lib.')
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->related('button', 'button-group')
    ->note('bootstrap', 'Usa il dropdown di Bootstrap (`data-bs-toggle="dropdown"`); le voci colorate hanno `text-<variante>`.')
    ->note('wonder', 'Usa `wi-dropdown-list` e `wi-dropdown-item` della lib; le voci colorate hanno `tx-<variante>`, il separatore è `wi-dropdown-divider`.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            Dropdown::make('Azioni')
                ->item('Modifica', '/edit/12')
                ->item('Duplica', '/duplicate/12')
                ->divider()
                ->item('Elimina', '/delete/12', ['variant' => 'danger'])
            PHP)
            ->height(230)
    )
    ->example(
        Example::make('Voci di ogni tipo')
            ->code(<<<'PHP'
            Dropdown::make('Record')
                ->variant('secondary')
                ->outline()
                ->header('Esporta')
                ->item('CSV', '/export.csv', ['blank' => true, 'icon' => 'bi bi-filetype-csv'])
                ->item('PDF', '/export.pdf', ['blank' => true, 'icon' => 'bi bi-filetype-pdf'])
                ->divider()
                ->action('Copia link', ['data-copy' => '/p/12'], ['icon' => 'bi bi-link-45deg'])
                ->text('Ultimo salvataggio: oggi')
                ->item('Disattivato', '#', ['disabled' => true])
            PHP)
            ->description('`header()` e `text()` non sono cliccabili; `action()` è un `<button type="button">` per il JavaScript della pagina.')
            ->height(320)
    )
    ->example(
        Example::make('Azione POST con conferma')
            ->code(<<<'PHP'
            Dropdown::make('Altro')
                ->item('Pubblica', '/backend/publish/12', ['method' => 'post', 'variant' => 'success'])
                ->item('Elimina', '/backend/delete/12', [
                    'method' => 'post',
                    'variant' => 'danger',
                    'confirm' => 'Eliminare il record?',
                    'confirm_ok' => 'Elimina',
                ])
            PHP)
            ->description('Ogni voce POST è un `<form method="post">` con il token CSRF; la conferma usa gli attributi `data-wi-confirm*`.')
            ->height(200)
    )
    ->example(
        Example::make('Direzione, allineamento e dimensione')
            ->code(<<<'PHP'
            Dropdown::make('Su, a destra')
                ->size('sm')
                ->direction('up')
                ->align('end')
                ->item('Prima voce', '#')
                ->item('Seconda voce', '#')
            PHP)
            ->height(200)
    );
