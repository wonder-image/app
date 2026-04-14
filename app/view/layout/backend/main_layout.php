<?php \Wonder\View\View::layout('backend.base'); ?>
    
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <?=$PAGE_CONTENT?>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>

<?php \Wonder\View\View::end(); ?>
