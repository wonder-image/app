<?php
use Wonder\Elements\Components\Button;
use Wonder\Http\Route;
// Con `add` (href, modal, label) la scheda è il link per aggiungere un indirizzo: stessa cornice delle altre.
$add = (array) ($add ?? []);
if ($add !== []):
    $modal = trim((string) ($add['modal'] ?? ''));
?>
<a class="wi-address-card wi-address-card--add b-2 p-4" href="<?=e((string) ($add['href'] ?? ''))?>"<?php if ($modal !== ''): ?> data-wi-modal-target="#<?=e($modal)?>" aria-haspopup="dialog" aria-controls="<?=e($modal)?>"<?php endif; ?>>
    <i class="title bi bi-plus-lg a-c" aria-hidden="true"></i>
    <span class="text a-c"><?=e((string) ($add['label'] ?? ''))?></span>
</a>
<?php
    return;
endif;
$card = (array) ($card ?? []);
$id = (int) ($card['id'] ?? 0);
$phone = trim((string) ($card['phone'] ?? ''));
$trash = Button::make('')->type('button')->variant('black')->addClass('btn-icon')->icon('bi bi-trash')
    ->attr('aria-label', (string) __t('account.addresses.delete'))
    ->opensModal('account-address-delete-'.$id);
$edit = Button::to(Route::url('account.addresses.edit', ['id' => $id]), (string) __t('account.actions.edit'))
    ->variant('black')->outline()->icon('bi bi-pencil')
    ->opensModal('account-address-'.$id);
?>
<div class="wi-address-card p-4 bg-gray">
    <div class="wi-address-card__body">
        <div class="subtitle"><?=e((string) ($card['name'] ?? ''))?></div>
        <div class="text w-100 mt-1">
            <?php if ($phone !== ''): ?><div class="w-100"><?=e($phone)?></div><?php endif; ?>
            <?php foreach ((array) ($card['lines'] ?? []) as $line): ?>
                <div class="w-100"><?=e((string) $line)?></div>
            <?php endforeach; ?>
        </div>
    </div>
    <div class="d-flex gap-2 j-content-end">
        <?=$trash?>
        <?=$edit?>
    </div>
</div>
