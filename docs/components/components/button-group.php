<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\ButtonGroup;
use Wonder\Elements\Components\Dropdown;

return ComponentDoc::for(ButtonGroup::class)
    ->title('ButtonGroup')
    ->group('action')
    ->order(20)
    ->tags('gruppo', 'bottoni', 'toolbar')
    ->description('Un gruppo di bottoni, badge o dropdown in fila. `vertical()` li impila, `toolbar()` rende un `role="toolbar"` e `label()` dà il nome accessibile; `size()` vale per tutti i figli.')
    ->uses(Button::class, Dropdown::class)
    ->docs('concetti/componenti/README.md#esempio-button-badge-group-dropdown', 'Componenti UI')
    ->related('button', 'dropdown', 'badge')
    ->example('Base', <<<'PHP'
    ButtonGroup::make([
        Button::make('Sinistra')->variant('secondary')->outline(),
        Button::make('Centro')->variant('secondary')->outline(),
        Button::make('Destra')->variant('secondary')->outline(),
    ])->label('Allineamento')
    PHP)
    ->example(
        Example::make('Con un dropdown')
            ->code(<<<'PHP'
            ButtonGroup::make([
                Button::make('Annulla')->variant('secondary')->outline(),
                Button::make('Pubblica')->variant('primary'),
                Dropdown::make('Altro')
                    ->variant('secondary')
                    ->outline()
                    ->item('Duplica', '/duplicate')
                    ->item('Esporta CSV', '/export.csv', ['blank' => true])
                    ->divider()
                    ->item('Elimina', '/delete/12', ['variant' => 'danger']),
            ])->label('Azioni record')
            PHP)
            ->description('Il menu azioni tipico di una scheda: bottoni e un `Dropdown` nello stesso gruppo.')
            ->height(220)
    )
    ->example('Verticale e dimensioni', <<<'PHP'
    ButtonGroup::make([
        Button::make('Primo'),
        Button::make('Secondo'),
        Button::make('Terzo'),
    ])->vertical()->size('sm')
    PHP, '`add()` aggiunge un figlio alla volta, se il gruppo si costruisce a passi.');
