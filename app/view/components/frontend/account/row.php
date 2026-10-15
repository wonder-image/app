<?php
use Wonder\Elements\Components\Button;
$columns = array_values(array_filter((array) ($columns ?? []), 'is_array'));
$action = (array) ($action ?? []);
$actionLabel = trim((string) ($action['label'] ?? ''));
$modal = trim((string) ($action['modal'] ?? ''));
$href = trim((string) ($action['href'] ?? ''));
$hint = trim((string) ($action['hint'] ?? ''));
$button = null;
if ($actionLabel !== '' && ($modal !== '' || $href !== '' || !empty($action['disabled']))) {
    $button = Button::to($modal !== '' ? '#' : $href, $actionLabel)->variant('gray');
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
            <div class="d-grid col-1 gap-1 text">
                <div class="fw-500"><?=e((string) ($column['label'] ?? ''))?></div>
                <div class=""><?=e((string) ($column['value'] ?? ''))?></div>
            </div>
        <?php endforeach; ?>
    </div>
    <div class="d-flex j-content-end gap-1">
        <div>
            <?=$button?->render()?>
            <?php if ($hint !== ''): ?><br><span class="text-small"><?=e($hint)?></span><?php endif; ?>
        </div>
    </div>
</div>
