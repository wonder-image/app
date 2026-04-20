<?php

$DOCUMENT = infoLegalDocument($ITEM['id'] ?? 0);
$documentTitle = trim((string) ($DOCUMENT->renderName ?? $DOCUMENT->name ?? 'Documento legale'));

if ($documentTitle === '') {
    $documentTitle = trim((string) ($DOCUMENT->doc_type ?? 'Documento legale'));
}

$downloadUrl = __r('backend.resource.'.RESOURCE_CLASS::slug().'.download', [
    'id' => $DOCUMENT->id ?? 0,
]);

\Wonder\View\View::layout('backend.show');
?>

<div class="row g-3">
    <wi-card class="col-4">
        <h6 class="col-12 mb-2">Documento</h6>
        <div class="col-12">
            Nome: <b><?=htmlspecialchars($documentTitle, ENT_QUOTES, 'UTF-8')?></b><br>
            Tipologia: <b><?=htmlspecialchars((string) ($DOCUMENT->doc_type ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
            Versione: <b><?=htmlspecialchars((string) ($DOCUMENT->version ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
            Lingua: <b><?=htmlspecialchars((string) ($DOCUMENT->language_code ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
            Pubblicato: <b><?=htmlspecialchars((string) ($DOCUMENT->published_at ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
            Stato: <b><?=active($DOCUMENT->active ?? '', $DOCUMENT->id ?? 0)->badge ?? ($DOCUMENT->active ?? '')?></b>
        </div>
        <?php if ($downloadUrl !== ''): ?>
            <div class="col-12 mt-3">
                <a href="<?=$downloadUrl?>" target="_blank" rel="noopener noreferrer" class="btn btn-dark btn-sm">
                    <i class="bi bi-download"></i> Scarica PDF
                </a>
            </div>
        <?php endif; ?>
    </wi-card>

    <wi-card class="col-8">
        <h6 class="col-12 mb-2">Checkbox</h6>
        <div class="col-12">
            <?= wiCard($DOCUMENT->renderLabel ?? '') ?>
        </div>
    </wi-card>

    <wi-card class="col-12">
        <h6 class="col-12 mb-2">Testo documento</h6>
        <div class="col-12">
            <?= wiCard($DOCUMENT->renderContent ?? '') ?>
        </div>
    </wi-card>
</div>
