<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\RichText;

return ComponentDoc::for(RichText::class)
    ->title('RichText')
    ->group('content')
    ->order(20)
    ->tags('html', 'formattato', 'editor')
    ->description('Un testo già in HTML, per esempio quello scritto con l\'editor: viene stampato così com\'è, senza escape. Usalo solo con markup fidato o già pulito da `SafeHtml::clean()`.')
    ->docs('concetti/componenti/README.md', 'Componenti UI')
    ->related('text')
    ->example('Base', <<<'PHP'
    RichText::make('<p>Un paragrafo con <strong>grassetto</strong>, <em>corsivo</em> e un <a href="#">link</a>.</p>')
    PHP)
    ->example('Come blocco', <<<'PHP'
    RichText::make('<ul><li>Prima voce</li><li>Seconda voce</li></ul>')->tag('div')
    PHP, '`tag()` sceglie l\'elemento contenitore.');
