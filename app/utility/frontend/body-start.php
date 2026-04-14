<?php if ($ANALYTICS->tag_manager->active) { ?>
<!-- Google Tag Manager (noscript) -->
<noscript>
    <iframe src="https://www.googletagmanager.com/ns.html?id=<?=$ANALYTICS->tag_manager->id?>" height="0" width="0" style="display:none;visibility:hidden"></iframe>
</noscript>
<!-- End Google Tag Manager (noscript) -->
<?php } ?>

<?php include $ROOT.'/custom/utility/frontend/body-start.php'; ?>