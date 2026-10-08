<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Steps;

return ComponentDoc::for(Steps::class)
    ->title('Steps')
    ->group('choice')
    ->order(30)
    ->tags('passi', 'wizard', 'checkout', 'breadcrumb')
    ->description('Un percorso a passi: fatto, in corso, da fare. Solo un passo `done` con un `href` diventa un link; il passo `current` ha `aria-current="step"`, i passi `todo` sono spenti. L\'etichetta di `make()` è l\'`aria-label` del `<nav>`.')
    ->docs('concetti/componenti/README.md#choice-choicegroup-e-steps', 'Componenti UI')
    ->related('choice-group')
    ->note('wonder', 'Usa `.wi-steps` della lib.')
    ->note('bootstrap', 'Rende un `breadcrumb`.')
    ->example('Base', <<<'PHP'
    Steps::make('Checkout')
        ->step('Carrello', '/cart/', 'done')
        ->step('Spedizione', '/checkout/', 'current')
        ->step('Pagamento')
    PHP)
    ->example('Tutti fatti tranne l\'ultimo', <<<'PHP'
    Steps::make('Registrazione')
        ->step('Account', '/signup/', 'done')
        ->step('Profilo', '/signup/profile/', 'done')
        ->step('Conferma', null, 'current')
    PHP, 'Un valore di stato diverso da `done` e `current` vale `todo`.');
