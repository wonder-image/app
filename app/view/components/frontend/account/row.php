<?php
use Wonder\Elements\Components\Button;
$label = trim((string) ($label ?? ''));
$value = array_values(array_filter(array_map('strval', (array) ($value ?? []))));
?>
<article class="d-grid col-3 col-p-1 gap-4 pb-5 mb-5" style="border-bottom:1px solid var(--tx-color-10,var(--input-border-color));align-items:center;">
    <div class="col-2 col-p-1">
        <h3 class="text fw-500 mb-2"><?=e($label)?></h3>
        <?php foreach ($value as $line): ?><p class="text-small"><?=e($line)?></p><?php endforeach; ?>
    </div>
    <?php if (!empty($href) && !empty($action)): ?>
        <div class="d-flex j-content-end j-content-p-start">
            <?=isset($action_component) && $action_component instanceof \Wonder\Elements\Component
                ? $action_component->render()
                : Button::to($href, $action)->outline()->icon('bi bi-pencil')->size('sm')->render()?>
        </div>
    <?php endif; ?>
</article>
