<?php \Wonder\View\View::layout('backend.base'); ?>
    
    <?= \Wonder\View\View::component('backend.layout.header') ?>

    <?=$PAGE_CONTENT?>

    <?= \Wonder\View\View::component('backend.layout.footer') ?>

<?php \Wonder\View\View::end(); ?>
