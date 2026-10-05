<?php if ($ANALYTICS->tag_manager->active) { ?>
<!-- Google Tag Manager (noscript) -->
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=<?=e($ANALYTICS->tag_manager->id)?>" height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->
<?php } ?>
<?= \Wonder\View\View::component('frontend.overlay.impersonation') ?>
<?php $dataLayerFlags = JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT | JSON_THROW_ON_ERROR; ?>
<script>
window.dataLayer = window.dataLayer || [];
window.dataLayer.push(<?=json_encode(['user' => ['id' => (int) ($_SESSION['user_id'] ?? 0) > 0 ? (int) $_SESSION['user_id'] : null]], $dataLayerFlags)?>);
<?php foreach (\Wonder\Auth\Frontend\AuthSession::consumeEvents() as $authEvent): ?>
window.dataLayer.push(<?=json_encode($authEvent, $dataLayerFlags)?>);
<?php endforeach; ?>
</script>
