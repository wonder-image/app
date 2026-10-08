<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Form\Components\InputIcon;

return ComponentDoc::for(InputIcon::class)
    ->title('Input icona')
    ->group('text')
    ->order(100)
    ->tags('icona', 'icon', 'bootstrap icons', 'picker', 'input')
    ->description('Il nome di un\'icona Bootstrap Icons (`bi-star`). Nel backend mostra l\'anteprima a sinistra, il nome al centro e a destra un bottone che apre la raccolta con la ricerca, anche in italiano («stella», «goccia»): la griglia la monta la lib con `data-wi-icon-picker`, che aggiorna anche l\'anteprima mentre si scrive. Nel frontend rende come un `InputText` semplice. Il valore resta un testo: `OptionVisual::icon()` lo normalizza (minuscolo, `bi-` aggiunto se manca) e un nome non valido mostra `bi-question-square`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: campi di testo')
    ->docs('concetti/form/form-field.md#opzioni-con-un-segno', 'FormField: opzioni con un segno')
    ->related('input-text', 'input-color')
    ->note('bootstrap', 'Niente `form-floating`: label `h6 form-label` sopra, `input-group wi-icon-picker` con anteprima, input (placeholder `bi-star`, `autocomplete="off"`) e bottone `data-wi-icon-picker-open`.')
    ->note('wonder', 'Rende con il renderer dell\'`InputText`: nessuna anteprima e nessuna raccolta, resta un campo di testo con il nome dell\'icona.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            (new InputIcon('icon'))
                ->label('Icona')
                ->value('bi-star')
            PHP)
            ->description('Nel backend il bottone a destra apre la raccolta sopra il campo.')
            ->height(340)
    )
    ->example('Senza valore', <<<'PHP'
    (new InputIcon('icon'))
        ->label('Icona del servizio')
    PHP, 'Senza valore l\'anteprima mostra `bi-question-square`.')
    ->example('Con errore', <<<'PHP'
    (new InputIcon('icon'))
        ->label('Icona')
        ->value('bi-stella')
        ->error('Icona sconosciuta: scegline una dalla raccolta.')
    PHP, 'Un nome che non esiste nella raccolta non si rompe: l\'anteprima resta il segnaposto e il messaggio di `error()` spiega cosa fare.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('icon')->icon()->label('Icona')->value('bi-heart-fill')
    PHP, '`icon()` ritorna `Inputs\InputIcon`, con i soli modificatori universali.');
