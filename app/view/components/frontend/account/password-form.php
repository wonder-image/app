<?php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Auth\Frontend\AccountPassword;
use Wonder\Elements\Components\Button;
$form_id ??= 'update_password';
$action ??= '';
$cancel_url ??= '';
$has_password ??= true;
$fields ??= AccountPassword::fields((bool) $has_password);
?>
<p class="text mb-5"><?=e(__t($has_password ? 'account.password.intro' : 'account.password.intro_missing'))?></p>
<form id="<?=e($form_id)?>" action="<?=e($action)?>" method="post" class="d-grid col-2 col-p-1 gap-4" novalidate>
    <?=FormField::key('csrf_token')->hidden()->value($csrf_token)?>
    <?php foreach ((array) $fields as $key => $field): ?>
        <div class="col-<?=$key === 'current_password' ? 2 : 1?> col-p-1"><?=$field?></div>
    <?php endforeach; ?>
    <div class="d-flex j-content-end gap-3 col-2 col-p-1">
        <?=Button::to($cancel_url, (string) __t('account.actions.cancel'))->outline()->render()?>
        <?=Button::make((string) __t('account.actions.save'))->type('submit')->render()?>
    </div>
</form>
