<?php

use Wonder\Auth\Frontend\AccountPagination;

/** @var array{page: int, pages: int, from: int, to: int, total: int} $pagination */
/** @var string $base_url */

if ((int) ($pagination['total'] ?? 0) <= 0) {
    return;
}

$item = static function (int $page, string $label, string $aria = '', bool $current = false, bool $disabled = false) use ($base_url): string {
    $attributes = $disabled
        ? ' aria-disabled="true" tabindex="-1"'
        : ' href="'.e(AccountPagination::url($base_url, $page)).'"'.($current ? ' aria-current="page"' : '');

    return '<li><a class="wi-pagination__item"'.$attributes.($aria !== '' ? ' aria-label="'.e($aria).'"' : '').'>'.$label.'</a></li>';
};
?>
<div class="wi-row-table__foot">
    <span><?=e((string) __t('account.pagination.summary', ['from' => $pagination['from'], 'to' => $pagination['to'], 'total' => $pagination['total']]))?></span>
    <ul class="wi-pagination" aria-label="<?=e((string) __t('account.pagination.label'))?>">
        <?=$item($pagination['page'] - 1, '<i class="bi bi-chevron-left" aria-hidden="true"></i>', (string) __t('account.pagination.previous'), false, $pagination['page'] <= 1)?>
        <?php foreach (AccountPagination::window($pagination) as $page): ?>
            <?=$item($page, (string) $page, '', $page === $pagination['page'])?>
        <?php endforeach; ?>
        <?=$item($pagination['page'] + 1, '<i class="bi bi-chevron-right" aria-hidden="true"></i>', (string) __t('account.pagination.next'), false, $pagination['page'] >= $pagination['pages'])?>
    </ul>
</div>
