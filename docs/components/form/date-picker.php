<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\DatePicker;

return ComponentDoc::for(DatePicker::class)
    ->title('Date picker')
    ->group('date')
    ->order(20)
    ->tags('data', 'calendario', 'datepicker', 'dateInput', 'gg/mm/aaaa')
    ->description('Un campo di testo con il calendario della lib, in formato `gg/mm/aaaa`: il valore si legge e si scrive come `d/m/Y`, e `min()` / `max()` escono come `data-wi-min-date` / `data-wi-max-date` nello stesso formato. A differenza di `Date` il calendario è lo stesso in ogni browser.

Nel frontend l\'input porta `data-wi-date-picker="true"` e la lib lo inizializza con il datepicker jQuery UI in italiano (settimana dal lunedì, anni dal 1900); nel backend porta `data-wi-date="true"` e usa bootstrap-datepicker, con la label come placeholder. Nelle Resource si dichiara con `FormField::key(\'nome\')->dateInput($min, $max)`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#date-ora', 'FormField: date e ora')
    ->docs('concetti/form/theme-system.md', 'Sistema Form / Theme / Element')
    ->related('date', 'date-range', 'select-date')
    ->note('bootstrap', 'Il renderer scrive `placeholder` con il testo della label, dentro il `form-floating`. `min()` e `max()` escono come attributi, ma la lib del backend li passa a bootstrap-datepicker come `minDate` / `maxDate` su un\'istanza già creata e il calendario non li applica: i limiti valgono oggi solo nel frontend.')
    ->note('wonder', 'La lib apre il calendario al focus e segna il contenitore con `selector-show` finché resta aperto; un valore iniziale viene ripassato al picker con `setDate`. Il placeholder è fisso: `gg/mm/aaaa`.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new DatePicker('start_date'))
                ->label('Data di inizio')
            PHP)
            ->description('Clicca nel campo per aprire il calendario.')
            ->height(340)
    )
    ->example(
        Example::make('Valore e intervallo ammesso')
            ->code(<<<'PHP'
            (new DatePicker('booking_date'))
                ->label('Data prenotazione')
                ->value('15/10/2026')
                ->min('01/10/2026')
                ->max('31/12/2026')
                ->required()
            PHP)
            ->description('Valore e limiti nello stesso formato `gg/mm/aaaa` del campo: nel frontend il calendario disabilita i giorni fuori dall\'intervallo.')
            ->height(340)
    )
    ->example('Con errore di validazione', <<<'PHP'
    (new DatePicker('expiry_date'))
        ->label('Scadenza')
        ->value('31/02/2026')
        ->error('Inserisci una data valida.')
    PHP, 'L\'errore lato server passa da `error()`, come per ogni Field.')
    ->example(
        Example::make('Dal DSL delle Resource')
            ->code(<<<'PHP'
            FormField::key('opening_date')->dateInput('01/01/2020', '31/12/2030')->label('Data di apertura')->value('2026-10-05')
            PHP)
            ->description('`dateInput($min, $max)` costruisce questo Element e porta al formato del picker anche un valore ISO: `2026-10-05` diventa `05/10/2026`.')
            ->height(340)
    );
