<?php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Auth\Frontend\AccountAddressForm;
use Wonder\Elements\Components\Button;
$form_id ??= 'save_shipping_address';
$action ??= '';
$cancel_url ??= '';
$show_actions ??= true;
?>
<p class="text-small mb-4"><?=e(__t('account.validation.hint'))?></p>
<form id="<?=e($form_id)?>" action="<?=e($action)?>" method="post" novalidate>
    <?=FormField::key('csrf_token')->hidden()->value($csrf_token)?>
    <?=AccountAddressForm::layout((array) $fields)->render()?>
    <?php if ($show_actions): ?>
    <div class="d-flex j-content-end gap-3 mt-4">
        <?=Button::to($cancel_url, (string) __t('account.actions.cancel'))->outline()->render()?>
        <?=Button::make((string) __t('account.actions.save'))->type('submit')->render()?>
    </div>
    <?php endif; ?>
</form>
