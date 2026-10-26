<?php

use Wonder\App\Support\FrontendRouteCatalog;
use Wonder\App\Models\Communications\Popup;
use Wonder\Elements\Components\Button;
use Wonder\Elements\Components\Modal;

// Al massimo un popup per richiesta: i siti nati dallo scaffold lo rendono
// anche nel loro body-start, e due modal con lo stesso id non si chiudono.
if (!empty($GLOBALS['WI_POPUP_RENDERED'])) {
    return;
}

$pageKey = FrontendRouteCatalog::currentPageKey();

if ($pageKey === null) {
    return;
}

$popup = Popup::modalPayloadForPageKey($pageKey);

if ($popup === null) {
    return;
}

// Il Modal frontend vuole un id valido come selettore (i codici generati sono
// pop_xxxxxxx): con un codice scritto a mano non valido il popup si salta,
// invece di far fallire tutta la pagina.
if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_-]*$/', (string) ($popup['code'] ?? ''))) {
    return;
}

$sessionKey = $popup['code'];
$_SESSION[$sessionKey] = (int) ($_SESSION[$sessionKey] ?? 0);

$maxView = trim((string) ($popup['view'] ?? ''));
if ($maxView !== '' && $_SESSION[$sessionKey] >= (int) $maxView) {
    return;
}

$_SESSION[$sessionKey]++;
$GLOBALS['WI_POPUP_RENDERED'] = true;

$image = $popup['image'] ?? null;
$title = trim((string) ($popup['title'] ?? ''));
$url = trim((string) ($popup['url'] ?? ''));
$urlLabel = trim((string) ($popup['url_label'] ?? ''));

if ($urlLabel === '') {
    $urlLabel = __t('components.buttons.more');
}

$modal = Modal::make($title)
    ->id((string) $popup['code'])
    ->frontend()
    // Il server la manda già aperta: niente script di apertura.
    ->addClass('wi-show')
    ->headerClass('b-0')
    ->titleClass('subtitle')
    ->dialogClass((string) ($popup['content_class'] ?? ''))
    ->footerClass((string) ($popup['footer_class'] ?? 'b-0'));

if (is_string($image) && $image !== '') {
    $modal->components([__ri($image)->alt($title)->addClass('p-r f-start w-100')->skeleton(false)]);
}

if ($url !== '') {
    $modal->footer([Button::make($urlLabel, $url)->arrow()->addClass('wi-close-modal')]);
}

echo $modal->render();
