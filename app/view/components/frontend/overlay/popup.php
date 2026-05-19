<?php

use Wonder\App\Support\FrontendRouteCatalog;
use Wonder\App\Models\Communications\Popup;

$pageKey = FrontendRouteCatalog::currentPageKey();

if ($pageKey === null) {
    return;
}

$popup = Popup::modalPayloadForPageKey($pageKey);

if ($popup === null) {
    return;
}

$sessionKey = $popup['code'];
$_SESSION[$sessionKey] = (int) ($_SESSION[$sessionKey] ?? 0);

$maxView = trim((string) ($popup['view'] ?? ''));
if ($maxView !== '' && $_SESSION[$sessionKey] >= (int) $maxView) {
    return;
}

$_SESSION[$sessionKey]++;

$image = $popup['image'] ?? null;
$title = trim((string) ($popup['title'] ?? ''));
$url = trim((string) ($popup['url'] ?? ''));
$urlLabel = trim((string) ($popup['url_label'] ?? ''));

if ($urlLabel === '') {
    $urlLabel = __t('components.buttons.more');
}

$body = '';

$footer = null;
$showFooter = $url !== '';

if (is_string($image) && $image !== '') {
    $body = __ri($image)->alt($title)->addClass('p-r f-start w-100')->skeleton(false)->render();
}

if ($showFooter) {
    $footer = '
        <div class="btn-group j-content-end">'.
            \Wonder\View\View::component('ui.button', [
                'label' => $urlLabel,
                'href' => $url,
                'class' => 'wi-close-modal',
                'type' => 'primary',
                'iconClass' => 'bi bi-chevron-right',
            ])
        .'</div>';
}

echo \Wonder\View\View::component('overlay.modal', [
    'id' => $popup['code'],
    'title' => $title,
    'titleClass' => 'subtitle',
    'class' => 'bg-'.$popup['bg_color'].' tx-'.$popup['bg_color'].'-o',
    'headerClass' => 'b-0',
    'bodyClass' => 'no-scrollbar',
    'contentClass' => $popup['content_class'] ?? '',
    'footerClass' => $popup['footer_class'] ?? 'b-0',
    'body' => $body,
    'footer' => $footer,
    'showFooter' => $showFooter,
]);
?>
<script>window.addEventListener('load', () => { modal(<?=json_encode('#'.($popup['code'] ?? ''))?>); })</script>
