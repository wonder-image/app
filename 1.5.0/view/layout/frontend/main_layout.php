<?php \Wonder\View\View::layout('frontend.base'); ?>
    
    <?php include $ROOT.'/custom/utility/frontend/header.php' ?>

    <?=$PAGE_CONTENT?>

    <?php include $ROOT.'/custom/utility/frontend/footer.php' ?>

<?php \Wonder\View\View::end(); ?>
