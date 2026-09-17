<?php \Wonder\View\View::layout('backend.list'); ?>

<?php if (!empty($READONLY)) { ?>
<wi-card class="col-12">
    <div class="alert alert-warning mb-0"><?=htmlspecialchars((string) ($READONLY_NOTICE ?? ''), ENT_QUOTES, 'UTF-8')?></div>
</wi-card>
<?php } ?>

<?=$TABLE_HTML?>

<?php \Wonder\View\View::end(); ?>
