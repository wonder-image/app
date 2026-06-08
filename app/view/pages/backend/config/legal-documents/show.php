<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;
use Wonder\Elements\Components\Link;
use Wonder\Elements\Components\RichText;
use Wonder\Elements\Components\SectionTitle;

$DOCUMENT = infoLegalDocument($ITEM['id'] ?? 0);
$documentTitle = trim((string) ($DOCUMENT->renderName ?? $DOCUMENT->name ?? 'Documento legale'));

if ($documentTitle === '') {
    $documentTitle = trim((string) ($DOCUMENT->doc_type ?? 'Documento legale'));
}

$downloadUrl = __r('backend.resource.'.$RESOURCE_CLASS::slug().'.download', [
    'id' => $DOCUMENT->id ?? 0,
]);

$statusBadge = active($DOCUMENT->active ?? '', $DOCUMENT->id ?? 0)->badge
    ?? (string) ($DOCUMENT->active ?? '');

$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

$documentComponents = [
    SectionTitle::make('Documento'),
    new RichText(
        'Nome: <b>'.$esc($documentTitle).'</b><br>'
        .'Tipologia: <b>'.$esc((string) ($DOCUMENT->doc_type ?? '')).'</b><br>'
        .'Versione: <b>'.$esc((string) ($DOCUMENT->version ?? '')).'</b><br>'
        .'Lingua: <b>'.$esc((string) ($DOCUMENT->language_code ?? '')).'</b><br>'
        .'Pubblicato: <b>'.$esc((string) ($DOCUMENT->published_at ?? '')).'</b><br>'
        .'Stato: <b>'.$statusBadge.'</b>'
    ),
];

if ($downloadUrl !== '') {
    $documentComponents[] = '<div class="col-12 mt-2">'
        .Link::to($downloadUrl, 'Scarica PDF')
            ->icon('bi bi-download')
            ->class('btn btn-dark btn-sm')
            ->blank()
            ->render()
        .'</div>';
}

\Wonder\View\View::layout('backend.show');

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Card)->components($documentComponents)->columns(1)->columnSpan(4),

        (new Card)->components([
            SectionTitle::make('Checkbox'),
            (new RichText(wiCard($DOCUMENT->renderLabel ?? '')))->tag('div'),
        ])->columns(1)->columnSpan(8),

        (new Card)->components([
            SectionTitle::make('Testo documento'),
            (new RichText(wiCard($DOCUMENT->renderContent ?? '')))->tag('div'),
        ])->columns(1)->columnSpan(12),

    ])->columns(12)
);

\Wonder\View\View::end();
