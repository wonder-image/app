<?php

use Wonder\App\ResourceSchema\FormField;
use Wonder\Auth\Impersonation;

$impersonation = new Impersonation([]);
$state = $impersonation->current();

if ($state === null || $state->stop_url === '') {
    return;
}

$actor = infoUser($state->actor_user_id, 'id');
$subject = infoUser($state->subject_user_id, 'id');
$actorLabel = trim((string) ($actor->name ?? '').' '.(string) ($actor->surname ?? ''));
$subjectLabel = trim((string) ($subject->name ?? '').' '.(string) ($subject->surname ?? ''));
$csrf = FormField::key('csrf_token')->hidden()->value($impersonation->csrfToken())->render();
$label = static function (string $key, string $fallback): string {
    try {
        return (string) __t($key);
    } catch (\Throwable) {
        return $fallback;
    }
};
?>
<aside class="w-100 p-3" style="background:var(--danger-color);color:var(--danger-o-color);" role="status" aria-live="polite">
    <div class="d-grid col-2 col-p-1 gap-3">
        <div class="text-small">
            <strong><?=e($label('components.impersonation.active', 'Impersonificazione attiva'))?></strong>
            <?=e($label('components.impersonation.operating_as', 'Stai operando come'))?>
            <strong><?=e($subjectLabel)?></strong>
            · <?=e($label('components.impersonation.started_by', 'avviata da'))?>
            <strong><?=e($actorLabel)?></strong>
        </div>
        <form action="<?=e($state->stop_url)?>" method="post">
            <?=$csrf?>
            <button class="btn btn-dark btn-sm" type="submit">
                <?=e($label('components.impersonation.stop', 'Torna al tuo account'))?>
            </button>
        </form>
    </div>
</aside>
