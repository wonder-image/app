<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\SelectDate;

return ComponentDoc::for(SelectDate::class)
    ->title('Data con validazione')
    ->group('date')
    ->order(50)
    ->tags('data', 'calendario', 'validazione', 'selectDate', 'gg/mm/aaaa')
    ->description('Un `DatePicker` con la validazione inline del frontend storico `selectDate()`: a ogni `change` la lib controlla il formato `gg/mm/aaaa` e i limiti di `min()` / `max()`, e scrive l\'errore sotto il campo nominando la label ("La data di nascita deve essere maggiore del ..."). Il `DatePicker` base lascia invece il controllo al check globale del form.

Esiste solo nel tema Wonder: nel backend e nelle Resource usa `DatePicker` (`FormField::key(\'nome\')->dateInput()`).')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('date-picker', 'date')
    ->note('wonder', 'Il renderer carica Moment.js con `Dependencies::moment()` e scrive la label in minuscolo, senza asterisco, in `data-wi-date-label`: è il nome che compare nei messaggi. `min()` e `max()` vanno nel formato `gg/mm/aaaa` del valore, perché la lib li confronta con Moment; l\'errore blocca anche l\'invio tramite `setCustomValidity`.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new SelectDate('birth_date'))
                ->label('Data di nascita')
            PHP)
            ->description('Digita una data in un formato diverso da `gg/mm/aaaa` ed esci dal campo per vedere il messaggio.')
            ->height(340)
    )
    ->example(
        Example::make('Con limiti')
            ->code(<<<'PHP'
            (new SelectDate('delivery_date'))
                ->label('Data di consegna')
                ->value('15/10/2026')
                ->min('08/10/2026')
                ->max('31/12/2026')
                ->required()
            PHP)
            ->description('Fuori dall\'intervallo la lib scrive "La data di consegna* deve essere maggiore del 08/10/2026" o "minore del 31/12/2026"; il calendario disabilita gli stessi giorni.')
            ->height(340)
    )
    ->example('Con errore dal server', <<<'PHP'
    (new SelectDate('start_date'))
        ->label('Inizio')
        ->value('01/10/2026')
        ->error('La data scelta non è più disponibile.')
    PHP, 'L\'errore di `error()` compare al caricamento; la validazione della lib lo sostituisce al primo `change`.');
