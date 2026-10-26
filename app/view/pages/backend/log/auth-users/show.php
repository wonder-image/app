<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;

\Wonder\View\View::layout('backend.show');

$log = (object) ($ITEM ?? []);
$user = isset($log->user_id) ? infoUser((int) $log->user_id) : infoUser('');
$userName = ($user->exists ?? false) ? $user->fullName : 'Utente non definito';
$meta = $log->meta ?? '';

if (is_string($meta) && $meta !== '') {
    $decodedMeta = json_decode($meta, true);
    if (is_array($decodedMeta)) {
        $meta = $decodedMeta;
    }
}

$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Card)->components([
            '<h6>'.$esc((string) ($log->event ?? '')).'</h6>',
            '<div>'.$esc((string) $userName).'</div>',
        ])->columnSpan(12),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12">Dettagli</h6>',
                '<div class="col-6 mt-2">'
                    .'Evento: <b>'.$esc((string) ($log->event ?? '')).'</b><br>'
                    .'Area: <b>'.$esc((string) ($log->area ?? '')).'</b><br>'
                    .'IP: <b>'.$esc((string) ($log->ip ?? '')).'</b><br>'
                    .'User Agent: <b>'.$esc((string) ($log->user_agent ?? '')).'</b>'
                .'</div>',
            ]),
        ])->columnSpan(9),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12">Metadati</h6>',
                '<div class="col-12">'
                    .ResourceFormLayoutRenderer::renderLayout((new Card)->components([wiPreIfArray($meta)]))
                .'</div>',
            ]),
        ])->columnSpan(3),

    ])->columns(12)
);

\Wonder\View\View::end();
