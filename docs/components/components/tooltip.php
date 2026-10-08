<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Tooltip;

return ComponentDoc::for(Tooltip::class)
    ->title('Tooltip')
    ->group('feedback')
    ->order(30)
    ->tags('suggerimento', 'aiuto', 'icona')
    ->description('Un\'icona che al passaggio mostra un testo di aiuto. È quello che `SectionTitle::tooltip()` e `Modal::help()` mettono accanto al titolo.')
    ->uses(Container::class)
    ->docs('concetti/componenti/README.md', 'Componenti UI')
    ->related('help-text', 'section-title')
    ->example('Base', <<<'PHP'
    Tooltip::make('Il valore viene ricalcolato ogni notte.')
    PHP)
    ->example('Posizione e icona', <<<'PHP'
    (new Container())->columns(3)->gap(3)->components([
        Tooltip::make('Sopra (default)')->placement('top'),
        Tooltip::make('A destra')->placement('right')->icon('bi bi-question-circle'),
        Tooltip::make('Sotto')->placement('bottom')->icon('bi bi-exclamation-circle'),
    ])
    PHP, '`placement()` accetta `top`, `right`, `bottom` e `left`.');
