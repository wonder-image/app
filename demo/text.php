<?php

    $PRIVATE = false;
    $FRONTEND = true;
    $ROOT = $_SERVER['DOCUMENT_ROOT'];

    include $ROOT.'/app/wonder-image.php';

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <?php 
        
        $SEO->title = "Demo text";
        $SEO->description = "";

        include $ROOT_APP.'/utility/frontend/head.php';
        
    ?>

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>

    <section>
        <div class="content">
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
    </section>

    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>