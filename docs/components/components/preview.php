<?php

use Wonder\Docs\ComponentDoc;
use Wonder\Docs\Example;
use Wonder\Elements\Components\Preview;

return ComponentDoc::for(Preview::class)
    ->title('Preview')
    ->group('docs')
    ->order(20)
    ->tags('anteprima', 'iframe', 'sorgenti', 'chiaro', 'scuro')
    ->description('Una finestra di anteprima: un `<iframe>` con una barra per scegliere fra più sorgenti e, dove la sorgente lo supporta, passare da chiaro a scuro. Ogni sorgente è una pagina a sé con i propri asset: è così che il catalogo mostra Wonder e Bootstrap sulla stessa pagina. Una sorgente senza URL compare spenta con il motivo; `srcdoc` accetta HTML inline.')
    ->docs('concetti/componenti/catalogo.md', 'Catalogo dei componenti')
    ->related('code')
    ->note('bootstrap', 'La pagina dentro l\'iframe cambia schema con `postMessage({ type: \'wi-preview:scheme\' })` e comunica la sua altezza con `wi-preview:height`.')
    ->example(
        Example::make('Base')
            ->code(<<<'PHP'
            Preview::make('Due sorgenti inline')
                ->source('a', 'Versione A', null, ['srcdoc' => '<p style="font-family: sans-serif; padding: 1rem">Contenuto della sorgente <b>A</b>.</p>'])
                ->source('b', 'Versione B', null, ['srcdoc' => '<p style="font-family: sans-serif; padding: 1rem; background: #eef">Contenuto della sorgente <b>B</b>.</p>'])
                ->height(80)
            PHP)
            ->description('Qui dentro l\'anteprima è a sua volta un iframe: le sorgenti `srcdoc` non hanno bisogno di un URL.')
            ->height(180)
    )
    ->example(
        Example::make('Sorgente spenta e scelta iniziale')
            ->code(<<<'PHP'
            Preview::make('Con una sorgente non disponibile')
                ->source('wonder', 'Wonder', null, ['reason' => 'Nessun renderer nel tema Wonder.'])
                ->source('bootstrap', 'Bootstrap', null, ['srcdoc' => '<p style="font-family: sans-serif; padding: 1rem">Solo Bootstrap.</p>', 'schemes' => true])
                ->active('bootstrap')
                ->group('demo')
                ->height(80)
            PHP)
            ->description('`group()` sincronizza le anteprime con lo stesso gruppo e ricorda la scelta in `localStorage`; un selettore di pagina con `data-wi-preview-switch` le cambia tutte.')
            ->height(180)
    );
