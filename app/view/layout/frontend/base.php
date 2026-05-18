<!DOCTYPE html>
<html lang="<?=e(__l())?>">
<head>

    <?= \Wonder\View\View::component('frontend.layout.head') ?>

</head>
<body>

    <?= \Wonder\View\View::component('frontend.layout.body-start') ?>

    <?=$PAGE_CONTENT?>
    
    <?= \Wonder\View\View::component('frontend.layout.body-end') ?>
    
</body>
</html>
