<?php

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
?>

<div class="row g-3">
    <wi-card class="col-12">
        <h6><?=htmlspecialchars((string) $documentName, ENT_QUOTES, 'UTF-8')?></h6>
        <div><?=htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8')?></div>
    </wi-card>

    <div class="col-9">
        <div class="row g-3">
            <wi-card class="col-6">
                <h6 class="col-12 mb-2">Documento</h6>
                <div class="col-12">
                    Nome: <b><?=htmlspecialchars((string) $documentName, ENT_QUOTES, 'UTF-8')?></b><br>
                    Tipologia: <b><?=htmlspecialchars((string) ($document->doc_type ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Versione: <b><?=htmlspecialchars((string) ($document->version ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Lingua: <b><?=htmlspecialchars((string) ($document->language_code ?? ''), ENT_QUOTES, 'UTF-8')?></b>
                </div>
            </wi-card>

            <wi-card class="col-6">
                <h6 class="col-12 mb-2">Risposta utente</h6>
                <div class="col-12">
                    Fonte: <b><?=htmlspecialchars((string) $sourceText, ENT_QUOTES, 'UTF-8')?></b><br>
                    Azione: <?=$actionBadge?>
                </div>
            </wi-card>

            <wi-card class="col-12">
                <h6 class="col-12">Prova</h6>
                <div class="col-12">
                    <?=wiCard($evidence)?>
                </div>
            </wi-card>
        </div>
    </div>

    <div class="col-3">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12 mb-2">Altro</h6>
                <div class="col-12">
                    Lingua: <b><?=htmlspecialchars((string) ($event->locale ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    IP: <b><?=htmlspecialchars((string) ($event->ip_address ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Browser: <b><?=htmlspecialchars((string) ($event->user_agent ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Creazione: <b><?=htmlspecialchars((string) ($event->creation ?? ''), ENT_QUOTES, 'UTF-8')?></b>
                </div>
            </wi-card>
        </div>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
