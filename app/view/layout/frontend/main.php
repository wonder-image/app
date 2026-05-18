<?php \Wonder\View\View::layout('frontend.base'); ?>
    
    <?= \Wonder\View\View::component('frontend.layout.header') ?>

    <?=$PAGE_CONTENT?>

    <?= \Wonder\View\View::component('frontend.layout.footer') ?>

<?php \Wonder\View\View::end(); ?>
