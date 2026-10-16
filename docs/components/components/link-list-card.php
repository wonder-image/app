<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\LinkListCard;

return ComponentDoc::for(LinkListCard::class)
    ->title('LinkListCard')
    ->group('content')
    ->order(48)
    ->tags('link', 'elenco', 'navigazione', 'card', 'home')
    ->description('Card con intestazione (icona, titolo, conteggio) e un elenco di link. I link con un gruppo vengono raggruppati per intestazione nell\'ordine di arrivo e messi in colonne; quelli senza gruppo finiscono sotto `ungroupedTitle()` ("Generale"). `isGrouped()` dice se ci sono gruppi, per scegliere la larghezza della colonna esterna. Ogni link può avere un badge (un conteggio) e una riga secondaria: in home li fornisce `NavigationSchema::homeSummary()`.')
    ->docs('concetti/componenti/README.md#statuscard-linkcard-e-linklistcard', 'Componenti UI')
    ->related('link-card', 'status-card', 'card')
    ->example('Elenco semplice', <<<'PHP'
    LinkListCard::make('Contenuti')
        ->icon('bi-file-text')
        ->link('Pagine', '#')
        ->link('Articoli', '#')
    PHP)
    ->example('Con gruppi', <<<'PHP'
    LinkListCard::make('Dev')
        ->icon('bi-terminal')
        ->link('Riepilogo', '#')
        ->link('Pianificazioni', '#', 'Automazioni')
        ->link('Errori', '#', 'Log e diagnostica')
        ->link('Accessi', '#', 'Log e diagnostica')
    PHP, 'Ogni gruppo prende una colonna; senza gruppo il link va sotto "Generale".')
    ->example('Con badge e riga secondaria', <<<'PHP'
    LinkListCard::make('Immobili')
        ->icon('bi-house-door')
        ->link('Immobili', '#', '', '42', '30 in vendita · 12 in affitto')
        ->link('Residenze', '#', '', '8', '6 visibili · 2 in evidenza')
        ->link('Agenzie', '#')
    PHP, 'Quarto e quinto argomento di `link()`: badge accanto alla freccia e riga sotto il titolo.');
