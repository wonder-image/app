<?php
use Wonder\View\View;
$title ??= '';
$text ??= '';
$auth_profile ??= new \Wonder\Auth\Frontend\AuthProfile();
$authAlert = View::component('frontend.account.auth-errors', [
    'alert' => $alert ?? null,
    'errors' => (array) ($errors ?? []),
    'federated_error' => $federated_error ?? null,
    'validation_messages' => $validation_messages ?? null,
]);
View::layout($auth_profile->parentLayout());
?>
<main style="background:var(--auth-bg-color,var(--bg-color));color:var(--auth-tx-color,var(--tx-color));">
    <?=$authAlert?>
    <section>
        <div class="content content-little">
            <div style="background:var(--auth-form-bg-color,var(--auth-bg-color,var(--bg-color)));color:var(--auth-form-tx-color,var(--auth-tx-color,var(--tx-color)));">
                <?php if ($title !== ''): ?><h1 class="subtitle"><?=e($title)?></h1><?php endif; ?>
                <?php if ($text !== ''): ?><p class="text mt-3"><?=e($text)?></p><?php endif; ?>
                <?=$PAGE_CONTENT?>
            </div>
        </div>
    </section>
</main>
<?php View::end(); ?>
