<!DOCTYPE html>
<html lang="<?=__l()?>">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=htmlspecialchars((string) ($TITLE ?? ''), ENT_QUOTES, 'UTF-8')?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>

    <?=$PAGE_CONTENT?>

    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>
