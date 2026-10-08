<?php
use Wonder\Auth\Frontend\AccountAddressForm;
use Wonder\Elements\Components\Button;
use Wonder\Http\Csrf;
use Wonder\View\View;
$title ??= '';
$active ??= 'addresses';
$account_panel->layout(compact('title', 'active', 'navigation', 'errors', 'notice', 'modals', 'logout_url', 'logout_token', 'head', 'user'));
?>
<p class="text-small mb-4"><?=e((string) __t('account.validation.hint'))?></p>
<form method="post" action="<?=e((string) ($action ?? ''))?>" novalidate>
    <?=Csrf::field()->render()?>
    <?=AccountAddressForm::layout((array) ($fields ?? []))->render()?>
    <div class="mt-4">
        <?=Button::make((string) __t('account.actions.save'))->type('submit')->variant('black')->block()->addClass('wi-input-submit wi-submit')->render()?>
    </div>
    <p class="mt-4"><a href="<?=e((string) ($back_url ?? ''))?>"><?=e((string) __t('account.addresses.back'))?></a></p>
</form>
<?php View::end(); ?>
