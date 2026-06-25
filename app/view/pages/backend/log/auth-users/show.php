<?php

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
?>

<div class="row g-3">
    <wi-card class="col-12">
        <h6><?=htmlspecialchars((string) ($log->event ?? ''), ENT_QUOTES, 'UTF-8')?></h6>
        <div><?=htmlspecialchars((string) $userName, ENT_QUOTES, 'UTF-8')?></div>
    </wi-card>

    <div class="col-9">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12">Dettagli</h6>
                <div class="col-6 mt-2">
                    Evento: <b><?=htmlspecialchars((string) ($log->event ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    Area: <b><?=htmlspecialchars((string) ($log->area ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    IP: <b><?=htmlspecialchars((string) ($log->ip ?? ''), ENT_QUOTES, 'UTF-8')?></b><br>
                    User Agent: <b><?=htmlspecialchars((string) ($log->user_agent ?? ''), ENT_QUOTES, 'UTF-8')?></b>
                </div>
            </wi-card>
        </div>
    </div>

    <div class="col-3">
        <div class="row g-3">
            <wi-card class="col-12">
                <h6 class="col-12">Metadati</h6>
                <div class="col-12">
                    <?=wiCard($meta)?>
                </div>
            </wi-card>
        </div>
    </div>
</div>

<?php \Wonder\View\View::end(); ?>
