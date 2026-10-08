<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\DateTimeRange;

return ComponentDoc::for(DateTimeRange::class)
    ->title('Intervallo data e ora')
    ->group('date')
    ->order(40)
    ->tags('intervallo', 'data', 'ora', 'datetime', 'range', 'orario')
    ->description('La versione data e ora di `DateRange`: due input `gg/mm/aaaa h:m` inviati come `<nome>-from` e `<nome>-to`, con `value()` che prende la coppia `[da, a]` e `min()` / `max()` nello stesso formato.

Esiste solo nel tema Wonder: il contenitore porta `data-wi-date-time-range="true"` e la lib inizializza il plugin jQuery `datetimepicker` (ore e minuti a tendina, passo di 5 minuti). Non ha un helper nel DSL delle Resource; nel backend si usano `DateRange` o due `InputDatetime`.')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('date-range', 'date-picker', 'input-datetime')
    ->note('wonder', 'I nomi dei due input usano il trattino (`-from` / `-to`), a differenza di `DateRange` che usa il trattino basso (`_from` / `_to`): chi legge il POST deve tenerne conto. Gli input non sono `readonly`, quindi data e ora si possono anche digitare.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new DateTimeRange('shift'))
                ->label('Turno')
            PHP)
            ->description('I due input si chiamano `shift-from` e `shift-to`; clicca su uno dei due per aprire calendario e orario.')
            ->height(380)
    )
    ->example(
        Example::make('Valori e limiti')
            ->code(<<<'PHP'
            (new DateTimeRange('event'))
                ->label('Evento')
                ->value(['10/10/2026 09:00', '10/10/2026 18:30'])
                ->min('01/10/2026 00:00')
                ->max('31/12/2026 23:59')
            PHP)
            ->description('`value()` vuole due stringhe `gg/mm/aaaa HH:mm`; i limiti seguono lo stesso formato.')
            ->height(380)
    )
    ->example('Con errore di validazione', <<<'PHP'
    (new DateTimeRange('maintenance'))
        ->label('Manutenzione')
        ->value(['10/10/2026 18:00', '10/10/2026 09:00'])
        ->error('La fine deve seguire l\'inizio.')
    PHP, 'L\'errore riguarda il controllo intero e compare una volta sola, sotto i due input.');
