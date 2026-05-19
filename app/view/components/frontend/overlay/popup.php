<?php

use Wonder\App\Support\FrontendRouteCatalog;

$pageKey = FrontendRouteCatalog::currentPageKey();

if ($pageKey === null) {
    return;
}

$escapedPageKey = addslashes($pageKey);
$query = sqlSelect(
    'popup',
    'pages LIKE '."'%\\\"{$escapedPageKey}\\\"%'".' AND visible = \'true\' AND deleted = \'false\'',
    1,
    'position',
    'ASC',
    'id, view'
);

if (!$query->exists) {
    return;
}

$sessionKey = 'popup_'.$query->id;
$_SESSION[$sessionKey] = (int) ($_SESSION[$sessionKey] ?? 0);

$maxView = trim((string) ($query->row['view'] ?? ''));
if ($maxView !== '' && $_SESSION[$sessionKey] >= (int) $maxView) {
    return;
}

$_SESSION[$sessionKey]++;

$popup = info('popup', 'id', $query->id);
if (!is_object($popup)) {
    return;
}

$images = json_decode((string) ($popup->images ?? ''), true);
$image = is_array($images) ? ($images[0] ?? null) : null;
$title = trim((string) ($popup->title ?? ''));
$url = trim((string) ($popup->url ?? ''));
$urlLabel = trim((string) ($popup->url_label ?? ''));

if ($urlLabel === '') {
    $urlLabel = __t('components.buttons.more');
}

$body = '';

$footer = null;
$showFooter = $url !== '';

if (is_string($image) && $image !== '') {
    $body = __ri($image)->alt($title)->class('p-r f-start w-100')->skeleton(false)->render();
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
    'id' => 'popup',
    'title' => $title,
    'titleClass' => 'subtitle',
    'headerClass' => 'b-0',
    'bodyClass' => 'no-scrollbar',
    'footerClass' => 'b-0',
    'body' => $body,
    'footer' => $footer,
    'showFooter' => $showFooter,
]);
?>
<script>window.addEventListener('load', () => { modal('#popup'); })</script>
