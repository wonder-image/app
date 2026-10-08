<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Container;

return ComponentDoc::for(Alert::class)
    ->title('Alert')
    ->group('feedback')
    ->order(10)
    ->tags('avviso', 'messaggio', 'toast', 'notifica')
    ->description('Un messaggio con titolo, livello e chiusura opzionale. I livelli sono `info`, `success`, `warning` ed `error`; nel backend diventa un toast Bootstrap, nel frontend la `wi-alert` della lib.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md#esempio-alert', 'Componenti UI')
    ->docs('concetti/notifiche.md', 'Notifiche')
    ->note('bootstrap', 'Il markup è un `.toast` con intestazione colorata: va chiuso con `data-bs-dismiss="toast"`.')
    ->example('Base', <<<'PHP'
    Alert::make('Operazione completata', 'success')
        ->title('Fatto')
    PHP)
    ->example('Livelli', <<<'PHP'
    (new Container())->gap(3)->components([
        Alert::make('Un\'informazione utile.', 'info')->title('Info'),
        Alert::make('Tutto bene.', 'success')->title('Successo'),
        Alert::make('Attenzione a questo dettaglio.', 'warning')->title('Avviso'),
        Alert::make('Qualcosa è andato storto.', 'error')->title('Errore'),
    ])
    PHP, 'Senza `title()` il titolo è il nome del livello.')
    ->example('Senza chiusura e con più righe', <<<'PHP'
    Alert::make("Prima riga del messaggio.\nSeconda riga, a capo.", 'warning')
        ->title('Leggimi')
        ->dismissible(false)
    PHP, 'Il messaggio è escapato; gli a capo diventano `<br>`.');
