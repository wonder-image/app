<?php
use Wonder\App\ResourceSchema\FormField;
use Wonder\Elements\Components\Button;
$active ??= '';
?>
<nav class="d-flex d-column d-p-row gap-2 o-auto" aria-label="<?=e(__t('account.navigation.label'))?>">
    <?php foreach ((array) ($items ?? []) as $item): ?>
        <?php $isActive = $active === ($item['key'] ?? ''); ?>
        <a class="d-flex gap-2 p-3 <?=$isActive ? 'bg-primary-10' : ''?>" href="<?=e($item['href'])?>"
            style="flex-shrink:0;white-space:nowrap;text-decoration:none;border-radius:var(--button-border-radius);border-left:3px solid <?=$isActive ? 'var(--primary-color)' : 'transparent'?>;"
            <?=$isActive ? 'aria-current="page"' : ''?>>
            <?php if (!empty($item['icon'])): ?><i class="<?=e($item['icon'])?> tx-primary" aria-hidden="true"></i><?php endif; ?>
            <span><?=e($item['label'])?></span>
        </a>
    <?php endforeach; ?>
    <?php if (!empty($logout_url)): ?>
        <form id="logout" method="post" action="<?=e($logout_url)?>" style="flex-shrink:0;">
            <?=FormField::key('csrf_token')->hidden()->value($csrf_token)?>
            <?=Button::make((string) __t('account.navigation.logout'))->type('submit')->icon('bi bi-box-arrow-right')->outline()->addClass('w-100')->render()?>
        </form>
    <?php endif; ?>
</nav>
