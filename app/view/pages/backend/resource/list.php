<?php \Wonder\View\View::layout('backend.list'); ?>

<?php if (!empty($READONLY)) { ?>
<wi-card class="col-12">
    <?=\Wonder\Elements\Components\Alert::make((string) ($READONLY_NOTICE ?? ''), 'warning')->inline()->addClass('mb-0')->render()?>
</wi-card>
<?php } ?>

<?=$TABLE_HTML?>

<?php \Wonder\View\View::end(); ?>
