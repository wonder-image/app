<?php
use Wonder\Elements\Components\Button;
$columns = array_values(array_filter((array) ($columns ?? []), 'is_array'));
$action = (array) ($action ?? []);
$actionLabel = trim((string) ($action['label'] ?? ''));
$modal = trim((string) ($action['modal'] ?? ''));
$href = trim((string) ($action['href'] ?? ''));
$hint = trim((string) ($action['hint'] ?? ''));
$button = null;
if ($actionLabel !== '' && ($modal !== '' || $href !== '')) {
    $button = Button::to($modal !== '' ? '#' : $href, $actionLabel)->outline()->variant('black')->size('sm');
    if (trim((string) ($action['icon'] ?? '')) !== '') {
        $button->icon((string) $action['icon']);
    }
    if (!empty($action['disabled'])) {
        $button->disabled();
    } elseif ($modal !== '') {
        $button->opensModal($modal);
    }
}
?>
<div class="wi-data-row">
    <div class="wi-data-row__cols">
        <?php foreach ($columns as $column): ?>
            <div class="wi-data-row__col">
                <div class="wi-data-row__label"><?=e((string) ($column['label'] ?? ''))?></div>
                <div class="wi-data-row__value"><?=e((string) ($column['value'] ?? ''))?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="wi-data-row__action">
        <?=$button?->render()?>
        <?php if ($hint !== ''): ?><small><?=e($hint)?></small><?php endif; ?>
    </div>
</div>
