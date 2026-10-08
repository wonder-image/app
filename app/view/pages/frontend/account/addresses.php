<?php
use Wonder\Elements\Components\Button;
use Wonder\Http\Route;
use Wonder\View\View;
$title ??= '';
$active ??= 'addresses';
$cards = array_values(array_filter((array) ($cards ?? []), 'is_array'));
$can_add = (bool) ($can_add ?? false);
$createUrl = Route::url('account.addresses.create');
$account_panel->layout(compact('title', 'active', 'navigation', 'errors', 'notice', 'modals', 'logout_url', 'logout_token', 'head', 'user'));
?>
<?php if ($cards !== []): ?>
    <div class="wi-address-grid">
        <?php foreach ($cards as $card): ?>
            <?=View::component('frontend.account.address-card', ['card' => $card])?>
        <?php endforeach; ?>
        <?php if ($can_add): ?>
            <a class="wi-address-card wi-address-card--add" href="<?=e($createUrl)?>" data-wi-modal-target="#account-address-new" aria-haspopup="dialog" aria-controls="account-address-new">
                <i class="wi-address-card__icon bi bi-plus-lg" aria-hidden="true"></i>
                <span><?=e((string) __t('account.addresses.add'))?></span>
            </a>
        <?php endif; ?>
    </div>
<?php else: ?>
    <div class="wi-empty-state">
        <i class="wi-empty-state__icon bi bi-geo-alt" aria-hidden="true"></i>
        <p><?=e((string) __t('account.addresses.empty'))?></p>
        <?php if ($can_add): ?>
            <?=Button::to($createUrl, (string) __t('account.addresses.add_first'))->variant('black')->opensModal('account-address-new')->render()?>
        <?php endif; ?>
    </div>
<?php endif; ?>
<?php View::end(); ?>
