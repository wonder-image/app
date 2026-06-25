<?php

\Wonder\View\View::layout('backend.show');

$mail = (object) ($ITEM ?? []);
$user = isset($mail->user_id) ? infoUser((int) $mail->user_id) : infoUser('');
$userName = ($user->exists ?? false) ? $user->fullName : 'Utente non definito';
$attachments = $mail->attachments ?? '';
$bodyText = $mail->body_text ?? '';
$errorMessage = $mail->error_message ?? '';
$serviceText = mailService((string) ($mail->service ?? ''))->text ?? '';
$statusBadge = mailLogStatus((string) ($mail->status ?? ''))->badge ?? '';

if (is_string($attachments) && $attachments !== '') {
    $decodedAttachments = json_decode($attachments, true);
    if (is_array($decodedAttachments)) {
        $attachments = $decodedAttachments;
    }
}
?>

<div class="row g-3">
    <wi-card class="col-12">
        <h6><?=htmlspecialchars((string) ($mail->subject ?? ''), ENT_QUOTES, 'UTF-8')?></h6>
        <div><?=htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8')?></div>
    </wi-card>

    <div class="col-9">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12 mb-2">Dettagli</h6>
                <div class="col-12">
                    Mittente: <b><?=htmlspecialchars((string) ($mail->from_email ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Risposta: <b><?=htmlspecialchars((string) ($mail->reply_to_email ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Destinatario: <b><?=htmlspecialchars((string) ($mail->to_email ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Template: <b><?=htmlspecialchars((string) ($mail->template ?? ''), ENT_QUOTES, 'UTF-8')?></b>
                </div>
            </wi-card>

            <wi-card class="col-6">
                <h6 class="col-12">Messaggio</h6>
                <div class="col-12">
                    <?=wiPreIfArray(nl2br((string) $bodyText))?>
                </div>
                <h6 class="col-12">Allegati</h6>
                <div class="col-12">
                    <?=wiPreIfArray($attachments)?>
                </div>
            </wi-card>

            <wi-card class="col-6">
                <h6 class="col-12">Errori</h6>
                <div class="col-12">
                    <?=wiPreIfArray($errorMessage)?>
                </div>
            </wi-card>
        </div>
    </div>

    <div class="col-3">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12 mb-2">Altro</h6>
                <div class="col-12">
                    Servizio: <b><?=htmlspecialchars((string) $serviceText, ENT_QUOTES, 'UTF-8')?></b><br>
                    IP: <b><?=htmlspecialchars((string) ($mail->ip ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Browser: <b><?=htmlspecialchars((string) ($mail->user_agent ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Stato: <b><?=$statusBadge?></b>
                </div>
            </wi-card>
        </div>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
