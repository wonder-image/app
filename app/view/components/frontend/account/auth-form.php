<?php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Button;
$form_id ??= $surface;
$columns = max(1, min(12, $auth_profile->columns($surface)));
?>
<form id="<?=e($form_id)?>" method="post" class="w-100 d-grid col-<?=$columns?> col-p-1 gap-6 mt-6" novalidate>
    <?=FormField::key('csrf_token')->hidden()->value($csrf_token)?>
    <?=FormField::key('continue')->hidden()->value((string) ($_POST['continue'] ?? $_GET['continue'] ?? ''))?>
    <?php if (isset($token)): ?><?=FormField::key('token')->hidden()->value($token)?><?php endif; ?>
    <?php foreach ((array) $fields as $key => $field): ?>
        <div class="col-<?=max(1, min($columns, $auth_profile->fieldSpan($surface, (string) $key)))?> col-p-1">
            <?=$field?>
            <?php if ($surface === 'login' && $key === 'password'): ?>
                <a class="text-small mt-2" href="<?=e($auth_profile->route('password.recovery'))?>"><?=e(__t('auth.login.forgot'))?></a>
            <?php endif; ?>
        </div>
    <?php endforeach; ?>
    <div class="col-<?=$columns?> col-p-1"><?=FormField::key('recaptcha')->recaptcha($auth_profile->captchaAction($surface))?></div>
    <?=Button::make((string) __t($submit_key))->type('submit')->addClass('w-100 wi-input-submit wi-submit col-'.$columns.' col-p-1')->render()?>
</form>
