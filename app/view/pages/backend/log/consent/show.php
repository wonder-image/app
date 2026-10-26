<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;

\Wonder\View\View::layout('backend.show');

$event = (object) ($ITEM ?? []);
$user = isset($event->user_id) ? infoUser((int) $event->user_id) : infoUser('');
$userName = ($user->exists ?? false) ? $user->fullName : 'Utente non definito';
$document = isset($event->legal_document_id) ? infoLegalDocument((int) $event->legal_document_id) : (object) [];
$documentName = trim((string) ($document->name ?? ''));

if ($documentName === '') {
    $documentName = trim((string) ($document->doc_type ?? 'Documento legale'));
}

$evidence = $event->evidence_json ?? '';
if (is_string($evidence) && $evidence !== '') {
    $decodedEvidence = json_decode($evidence, true);
    if (is_array($decodedEvidence)) {
        $evidence = $decodedEvidence;
    }
}

$actionBadge = consentEventAction((string) ($event->action ?? ''))->badge ?? '';
$sourceText = consentEventSource((string) ($event->source ?? ''))->text ?? '';

$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Card)->components([
            '<h6>'.$esc((string) $documentName).'</h6>',
            '<div>'.$esc((string) $userName).'</div>',
        ])->columnSpan(12),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12 mb-2">Documento</h6>',
                '<div class="col-12">'
                    .'Nome: <b>'.$esc((string) $documentName).'</b><br>'
                    .'Tipologia: <b>'.$esc((string) ($document->doc_type ?? '')).'</b><br>'
                    .'Versione: <b>'.$esc((string) ($document->version ?? '')).'</b><br>'
                    .'Lingua: <b>'.$esc((string) ($document->language_code ?? '')).'</b>'
                .'</div>',
            ])->columnSpan(6),

            (new Card)->components([
                '<h6 class="col-12 mb-2">Risposta utente</h6>',
                '<div class="col-12">'
                    .'Fonte: <b>'.$esc((string) $sourceText).'</b><br>'
                    .'Azione: '.$actionBadge
                .'</div>',
            ])->columnSpan(6),

            (new Card)->components([
                '<h6 class="col-12">Prova</h6>',
                '<div class="col-12">'
                    .ResourceFormLayoutRenderer::renderLayout((new Card)->components([wiPreIfArray($evidence)]))
                .'</div>',
            ])->columnSpan(12),
        ])->columns(12)->columnSpan(9),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12 mb-2">Altro</h6>',
                '<div class="col-12">'
                    .'Lingua: <b>'.$esc((string) ($event->locale ?? '')).'</b><br>'
                    .'IP: <b>'.$esc((string) ($event->ip_address ?? '')).'</b><br>'
                    .'Browser: <b>'.$esc((string) ($event->user_agent ?? '')).'</b><br>'
                    .'Creazione: <b>'.$esc((string) ($event->creation ?? '')).'</b>'
                .'</div>',
            ]),
        ])->columnSpan(3),

    ])->columns(12)
);

\Wonder\View\View::end();
