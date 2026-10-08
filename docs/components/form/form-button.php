<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Form\Components\Button;

return ComponentDoc::for(Button::class)
    ->slug('form-button')
    ->title('Button (form)')
    ->group('action')
    ->order(10)
    ->tags('bottone', 'campo', 'didascalia', 'modal', 'repeater')
    ->description('Un bottone fra i campi di un form, con una didascalia accanto che mostra il valore (per esempio «Filati Nord · 12,00 €»). Non è un dato: non stampa `name` né `id`, quindi non parte con il form e le righe clonate di un repeater non duplicano id. Cosa fa al clic lo dicono gli attributi (`data-bs-toggle`, `data-*`). Nelle Resource: `FormField::key(\'nome\')->button(\'Testo\')`.')
    ->uses(FormField::class)
    ->docs('concetti/form/form-field.md#testo', 'FormField: button()')
    ->related('button', 'modal', 'repeater')
    ->example('Base', <<<'PHP'
    (new Button('supplier'))
        ->label('Scegli fornitore')
        ->icon('bi bi-search')
        ->value('Filati Nord · 12,00 €')
    PHP, 'Il `value()` è la didascalia accanto al bottone.')
    ->example('Senza valore, con variante e dimensione', <<<'PHP'
    (new Button('cost'))
        ->label('Costo')
        ->variant('secondary')
        ->outline()
        ->size('sm')
        ->emptyCaption('Nessun costo impostato')
        ->attr('data-bs-toggle', 'modal')
        ->attr('data-bs-target', '#wi-cost-modal')
    PHP, '`emptyCaption()` è la didascalia quando il valore è vuoto; gli attributi aprono una `Modal`.')
    ->example('Dal DSL delle Resource', <<<'PHP'
    FormField::key('supplier')->button('Scegli fornitore')->label('Fornitore')
    PHP);
