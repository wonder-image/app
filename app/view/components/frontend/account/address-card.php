<?php
use Wonder\Elements\Components\Button;
use Wonder\Http\Route;
$card = (array) ($card ?? []);
$id = (int) ($card['id'] ?? 0);
$phone = trim((string) ($card['phone'] ?? ''));
$phoneHref = trim((string) ($card['phone_href'] ?? ''));
$trash = Button::make('')->type('button')->variant('black')->size('sm')->icon('bi bi-trash')
    ->attr('aria-label', (string) __t('account.addresses.delete'))
    ->opensModal('account-address-delete-'.$id);
$edit = Button::to(Route::url('account.addresses.edit', ['id' => $id]), (string) __t('account.actions.edit'))
    ->outline()->variant('black')->size('sm')->icon('bi bi-pencil')
    ->opensModal('account-address-'.$id);
?>
<div class="wi-address-card">
    <div class="wi-address-card__body">
        <div class="wi-address-card__name"><?=e((string) ($card['name'] ?? ''))?></div>
        <?php if ($phone !== ''): ?><div><a href="<?=e($phoneHref)?>" style="text-decoration:underline"><?=e($phone)?></a></div><?php endif; ?>
        <?php foreach ((array) ($card['lines'] ?? []) as $line): ?>
            <div><?=e((string) $line)?></div>
        <?php endforeach; ?>
    </div>
    <div class="wi-address-card__actions">
        <?=$trash->render()?>
        <?=$edit->render()?>
    </div>
</div>
