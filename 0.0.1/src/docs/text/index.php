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

        include $ROOT_APP.'/utility/frontend/head.php';
        
    ?>

    <link rel="stylesheet" href="<?=$PATH->appCss?>/docs/index.css">

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>
    <?php include $ROOT.'/docs/utility/header.php' ?>

    <div class="w-100">
        <div class="d-grid gap-4 w-100">
            <div class="title-big">
                .title-big
            </div>
            <div class="title">
                .title
            </div>
            <div class="subtitle">
                .subtitle
            </div>
            <div class="text">
                .text
            </div>
            <div class="text-small">
                .text-small
            </div>
        </div>
    </div>

    <?php include $ROOT.'/docs/utility/footer.php' ?>
    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>