<!DOCTYPE html>
<html lang="<?=e(__l())?>">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=e($TITLE ?? '')?></title>

    <?= \Wonder\View\View::component('backend.layout.head') ?>

</head>
<body>

    <?= \Wonder\View\View::component('backend.layout.body-start') ?>

    <?=$PAGE_CONTENT?>

    <?= \Wonder\View\View::component('backend.layout.body-end') ?>

</body>
</html>
