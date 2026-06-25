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
        
        $SEO->title = "Demo alert";
        $SEO->description = "";

        echo \Wonder\View\View::component('frontend.layout.head');
        
    ?>
    
    <link rel="stylesheet" href="<?=$PATH->appCss?>/docs/index.css">

</head>
<body>

    <?= \Wonder\View\View::component('frontend.layout.body-start') ?>
    <?php include $ROOT.'/docs/utility/header.php' ?>

    <div class="w-100">
        <div class="d-grid gap-4 w-100">
            <div class="title-big">
                Alert
            </div>
            <div class="d-grid col-1 gap-5">

            </div>
            <?php

                $NOTIFICATIONS = \Wonder\Localization\TranslationProvider::$translations['notifications']
                    ?? \Wonder\Localization\TranslationProvider::$defaultTranslations['notifications'];

                if (!is_array($NOTIFICATIONS)) { $NOTIFICATIONS = []; }

                foreach ($NOTIFICATIONS as $key => $value) {
                    echo "
                    <div class='w-100'>
                        <div class='subtitle tx-{$value['type']}'>
                            <b>$key</b> => {$value['title']}
                        </div>
                        <div class='text'>
                            {$value['text']}
                        </div>
                    </div>
                    ";
                }

            ?>
        </div>
    </div>
    
    <?php include $ROOT.'/docs/utility/footer.php' ?>
    <?= \Wonder\View\View::component('frontend.layout.body-end') ?>
    
</body>
</html>
