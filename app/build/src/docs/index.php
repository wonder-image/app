<?php

    $FRONTEND = true;
    $PRIVATE = false;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <?php 
        
        $SEO->title = "Demo text";
        $SEO->description = "";

        echo \Wonder\View\View::component('frontend.layout.head');
        
    ?>

    <link rel="stylesheet" href="<?=$PATH->appCss?>/docs/index.css">

</head>
<body>

    <?= \Wonder\View\View::component('frontend.layout.body-start') ?>
    <?php include $ROOT.'/docs/utility/header.php' ?>

    <div class="w-100">
        <div class="title">
            Wonder Image App v.<?=$APP_VERSION?>
        </div>
    </div>

    <?php include $ROOT.'/docs/utility/footer.php' ?>
    <?= \Wonder\View\View::component('frontend.layout.body-end') ?>
    
</body>
</html>
