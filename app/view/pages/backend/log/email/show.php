<?php

use Wonder\Backend\Support\ResourceFormLayoutRenderer;
use Wonder\Elements\Components\Card;
use Wonder\Elements\Components\Container;

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
$esc = static fn (string $value): string => htmlspecialchars($value, ENT_QUOTES, 'UTF-8');

echo ResourceFormLayoutRenderer::renderLayout(
    (new Container)->components([

        (new Card)->components([
            '<h6>'.$esc((string) ($mail->subject ?? '')).'</h6>',
            '<div>'.$esc((string) $userName).'</div>',
        ])->columnSpan(12),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12 mb-2">Dettagli</h6>',
                '<div class="col-12">'
                    .'Mittente: <b>'.$esc((string) ($mail->from_email ?? '')).'</b><br>'
                    .'Risposta: <b>'.$esc((string) ($mail->reply_to_email ?? '')).'</b><br>'
                    .'Destinatario: <b>'.$esc((string) ($mail->to_email ?? '')).'</b><br>'
                    .'Template: <b>'.$esc((string) ($mail->template ?? '')).'</b>'
                .'</div>',
            ])->columnSpan(12),

            (new Card)->components([
                '<h6 class="col-12">Messaggio</h6>',
                '<div class="col-12">'.wiPreIfArray(nl2br((string) $bodyText)).'</div>',
                '<h6 class="col-12">Allegati</h6>',
                '<div class="col-12">'.wiPreIfArray($attachments).'</div>',
            ])->columnSpan(6),

            (new Card)->components([
                '<h6 class="col-12">Errori</h6>',
                '<div class="col-12">'.wiPreIfArray($errorMessage).'</div>',
            ])->columnSpan(6),
        ])->columns(12)->columnSpan(9),

        (new Container)->components([
            (new Card)->components([
                '<h6 class="col-12 mb-2">Altro</h6>',
                '<div class="col-12">'
                    .'Servizio: <b>'.$esc((string) $serviceText).'</b><br>'
                    .'IP: <b>'.$esc((string) ($mail->ip ?? '')).'</b><br>'
                    .'Browser: <b>'.$esc((string) ($mail->user_agent ?? '')).'</b><br>'
                    .'Stato: <b>'.$statusBadge.'</b>'
                .'</div>',
            ]),
        ])->columnSpan(3),

    ])->columns(12)
);

\Wonder\View\View::end();
