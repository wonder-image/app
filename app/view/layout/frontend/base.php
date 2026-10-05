<!DOCTYPE html>
<html lang="<?=e(__l())?>">
<head>

    <?php if (\Wonder\Http\Csrf::active()) { ?><meta name="wi-csrf" content="<?=e(\Wonder\Http\Csrf::token())?>"><?php } ?>

    <?= \Wonder\View\View::component('frontend.layout.head') ?>

</head>
<body>

    <?= \Wonder\View\View::component('frontend.layout.body-start') ?>

    <?=$PAGE_CONTENT?>
    
    <?= \Wonder\View\View::component('frontend.layout.body-end') ?>
    
</body>
</html>
