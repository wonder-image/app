<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\DateRange;

return ComponentDoc::for(DateRange::class)
    ->title('Intervallo di date')
    ->group('date')
    ->order(30)
    ->tags('intervallo', 'periodo', 'da', 'a', 'range', 'dateRange')
    ->description('Un intervallo di date in un solo controllo: due input `gg/mm/aaaa` in sola lettura, inviati come `<nome>_from` e `<nome>_to`; `value()` prende la coppia `[da, a]` e `min()` / `max()` limitano entrambi i calendari.

Nel frontend i due input stanno nello stesso riquadro `wi-input-container daterange`, separati da un trattino, e la lib (`data-wi-date-range="true"`) apre il calendario su ciascuno; nel backend diventano un `input-group input-daterange` con i prefissi Dal / Al e la label sopra, fuori dal `form-floating`. Nelle Resource si dichiara con `FormField::key(\'nome\')->dateRange($min, $max)`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#date-ora', 'FormField: date e ora')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('date-picker', 'date-time-range')
    ->note('bootstrap', 'Il renderer salta il `form-floating`: la label esce come `<label class="h6 form-label">` sopra il gruppo. `data-wi-min-date` / `data-wi-max-date` stanno sul gruppo, non sui singoli input, e come per `DatePicker` bootstrap-datepicker non li applica ancora.')
    ->note('wonder', 'Il riquadro è uno solo: il secondo input è posizionato dal CSS della lib accanto al primo, con larghezze fisse. Gli input sono `readonly`: la data si sceglie solo dal calendario.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new DateRange('period'))
                ->label('Periodo')
            PHP)
            ->description('I due input si chiamano `period_from` e `period_to`.')
            ->height(360)
    )
    ->example(
        Example::make('Valori e limiti')
            ->code(<<<'PHP'
            (new DateRange('stay'))
                ->label('Soggiorno')
                ->value(['10/10/2026', '17/10/2026'])
                ->min('01/10/2026')
                ->max('31/12/2026')
            PHP)
            ->description('`value()` vuole un array di due date `gg/mm/aaaa`; i limiti valgono per entrambi i calendari.')
            ->height(360)
    )
    ->example('Con errore di validazione', <<<'PHP'
    (new DateRange('promo'))
        ->label('Validità promozione')
        ->value(['20/10/2026', '15/10/2026'])
        ->error('La data finale deve seguire quella iniziale.')
    PHP, 'L\'errore riguarda il controllo intero e compare una volta sola, sotto i due input.')
    ->example(
        Example::make('Dal DSL delle Resource')
            ->code(<<<'PHP'
            FormField::key('season')->dateRange('01/01/2026', '31/12/2026')->label('Stagione')->value(['2026-06-01', '2026-09-15'])
            PHP)
            ->description('`dateRange($min, $max)` costruisce questo Element e porta la coppia al formato `gg/mm/aaaa`; senza `value()` la ricostruisce dai POST `<nome>_from` / `<nome>_to`.')
            ->height(360)
    );
