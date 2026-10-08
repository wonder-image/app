<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Accordion;
use Wonder\Elements\Components\Alert;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Form\Components\InputText;

return ComponentDoc::for(Accordion::class)
    ->title('Accordion')
    ->group('layout')
    ->order(30)
    ->tags('collassabile', 'faq', 'sezione', 'dropdown-box')
    ->description('Una sezione che si apre e si chiude: un titolo, una descrizione semplice o dei componenti figli. `expanded()` la apre subito; `icon()`, `titleSize()` e `descriptionSize()` accettano solo le coppie di icone e i preset tipografici della lib.')
    ->uses(Alert::class, Container::class, InputText::class, Example::class)
    ->docs('concetti/componenti/README.md#accordion', 'Componenti UI')
    ->related('card', 'modal')
    ->note('wonder', 'Riusa `wi-dropdown-box`, `wi-dropdown-title wi-switcher` e `wi-dropdown-content` della lib; `icon()` accetta `plus`, `chevron` e `plus-lg`.')
    ->note('bootstrap', '`flush()` e `link()` sono varianti del solo renderer Bootstrap; dentro un form di Resource può contenere i campi con `columns()`.')
    ->example('Base', <<<'PHP'
    Accordion::make('Come funziona la spedizione?')
        ->description('Prepariamo e affidiamo il pacco al corriere entro due giorni lavorativi.')
    PHP)
    ->example('Aperto, con icona e tipografia', <<<'PHP'
    (new Container())->gap(2)->components([
        Accordion::make('Tempi di consegna')
            ->description('In Italia 2-3 giorni lavorativi, in Europa 4-6.')
            ->icon('chevron')
            ->titleSize('text')
            ->descriptionSize('text-small')
            ->expanded(),
        Accordion::make('Resi')
            ->description('Hai 14 giorni per restituire un articolo.')
            ->icon('chevron'),
    ])
    PHP)
    ->example('Con componenti figli', <<<'PHP'
    Accordion::make('Serve aiuto?')
        ->components([
            Alert::make('Contattaci e ti risponderemo al più presto.', 'info')->dismissible(false),
        ])
    PHP, 'I figli vengono resi con lo stesso tema dell\'accordion.')
    ->example(
        Example::make('Campi a griglia (form di Resource)')
            ->code(<<<'PHP'
            Accordion::make('Scheda tecnica')
                ->columns(12)
                ->components([
                    (new InputText('material'))->label('Materiale')->columnSpan(6),
                    (new InputText('country'))->label('Paese')->columnSpan(6),
                ])
            PHP)
            ->description('Con `columns()` l\'accordion è un contenitore come una `Card`: ogni figlio prende la sua colonna.')
            ->themes('bootstrap')
    )
    ->example(
        Example::make('Variante link')
            ->code(<<<'PHP'
            Accordion::make('Compila le informazioni avanzate')
                ->link()
                ->components([
                    (new InputText('sku'))->label('SKU'),
                    (new InputText('ean'))->label('EAN'),
                ])
            PHP)
            ->description('Il titolo diventa un bottone di testo con `bi-chevron-down` e il corpo non ha cornice: per un accordion dentro un riquadro già incorniciato.')
            ->themes('bootstrap')
    );
