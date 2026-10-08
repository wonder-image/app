<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Elements\Components\Code;
use Wonder\Elements\Components\Container;

return ComponentDoc::for(Code::class)
    ->title('Code')
    ->group('docs')
    ->order(10)
    ->tags('codice', 'snippet', 'copia', 'evidenziazione', 'terminale')
    ->description('Un blocco di codice con evidenziazione della sintassi lato server e bottone «copia». PHP passa dal tokenizer nativo; HTML, CSS, JS, JSON e bash da un riconoscimento leggero. Il testo è escapato e `textContent` restituisce il codice originale: è quello che il bottone copia negli appunti. CSS e script escono una volta per pagina.')
    ->uses(Container::class)
    ->docs('concetti/componenti/catalogo.md', 'Catalogo dei componenti')
    ->related('preview')
    ->example('Base', <<<'PHP'
    Code::make("use Wonder\\Elements\\Components\\Button;\n\necho Button::make('Salva')->variant('success');")
    PHP, 'Senza `title()` l\'intestazione mostra il linguaggio.')
    ->example('Terminale e altri linguaggi', <<<'PHP'
    (new Container())->gap(3)->components([
        Code::make("npm install wonder-image\nphp bin/docs.php", 'bash')->title('Terminal'),
        Code::make('<div class="card"><!-- contenuto --><a href="/x">Link</a></div>', 'html')->title('index.html'),
        Code::make('{"name": "wonder-image", "private": true, "version": 2}', 'json')->title('package.json'),
    ])
    PHP)
    ->example('Chiaro, numerato e con altezza massima', <<<'PHP'
    Code::make(implode("\n", array_map(fn ($i) => "\$riga{$i} = {$i};", range(1, 20))))
        ->scheme('light')
        ->lineNumbers()
        ->maxHeight('12rem')
        ->copyLabels('Copia tutto', 'Fatto')
    PHP, '`scheme(\'auto\')` segue `data-bs-theme` della pagina; `copy(false)` toglie il bottone.');
