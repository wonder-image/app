<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;

\Wonder\View\View::layout('backend.show');

$error = (object) ($ITEM ?? []);
$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Card)->components([
            '<h6>'.$esc((string) ($error->table ?? '')).'</h6>',
            '<div>Errore N° '.$esc((string) ($error->error_n ?? '')).'</div>',
        ])->columnSpan(12),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12">Errore</h6>',
                '<div class="col-12">'.wiPreIfArray((string) ($error->error ?? '')).'</div>',
            ]),
            (new Card)->components([
                '<h6 class="col-12">Query</h6>',
                '<div class="col-12">'.wiPreIfArray((string) ($error->query ?? '')).'</div>',
            ]),
        ])->columnSpan(8),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12">Contesto</h6>',
                '<div class="col-12">'
                    .'Tabella: <b>'.$esc((string) ($error->table ?? '')).'</b><br>'
                    .'Funzione:'
                    .'<div class="mt-2">'.wiPreIfArray((string) ($error->function ?? '')).'</div>'
                .'</div>',
            ]),
        ])->columnSpan(4),

    ])->columns(12)
);

\Wonder\View\View::end();
