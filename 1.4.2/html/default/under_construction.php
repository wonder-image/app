<!DOCTYPE html>
<html lang="it">
<head>

    <?php include $ROOT_APP.'/utility/frontend/head.php'; ?>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <style>
        :root {

            --spacer: 4px;
            --header-height: 80px;

            --bg-color: #ffffff;
            --tx-color: #000000;

            /* Default font */ 
            --font-family: "Montserrat", sans-serif;
            --font-size: 16px;
            --line-height: 16px;
            --font-weight: 400;

            /* Font titolo grande */
            --title-big-font-size: 65px;
            --title-big-line-height: 65px;
            --title-big-font-family: var(--font-family);
            --title-big-font-weight: 700;

            /* Font titolo */
            --title-font-size: 32px;
            --title-line-height: 32px;
            --title-font-family: var(--font-family);
            --title-font-weight: 500;

            /* Font sottotitolo */
            --subtitle-font-size: 24px;
            --subtitle-line-height: 24px;
            --subtitle-font-family: var(--font-family);
            --subtitle-font-weight: 400;

            /* Font testo */
            --text-font-size: 16px;
            --text-line-height: 20px;
            --text-font-family: var(--font-family);
            --text-font-weight: 300;

            /* Font testo piccolo */
            --text-small-font-size: 12px;
            --text-small-line-height: 12px;
            --text-small-font-family: var(--font-family);
            --text-small-font-weight: 300;

            /* Set-up bottoni */  
            --button-font-weight: 400;
            --button-font-size: 14px;
            --button-line-height: 16px;
            --button-border-radius: 5px;
            --button-border-width: 2px;
            
            /* Set-up badge */
            --badge-font-weight: 400;
            --badge-font-size: 12px;
            --badge-line-height: 12px;
            --badge-border-radius: 5px;
            --badge-border-width: 1px;

            /* primary */
            --primary-color: #1e90ff;
            --primary-o-color: #ffffff;
            --primary-color-rgb: 30, 144, 255;
            --primary-o-color-rgb: 255, 255, 255;

        }
    </style>

</head>
<body>

    <?php include $ROOT_APP.'/utility/frontend/body-start.php' ?>

    <section class="full-page h-p-auto">
        <div class="content">

            <div class="p-a w-50 end c-h p-p-r w-p-100 p-c-h-off">
                <img src="<?=$PATH->appAssets?>/images/under-construction.png" alt="Sito in costruzione" class="w-100">
            </div>

            <div class="p-a w-50 c-h p-p-r w-p-100 p-c-h-off mt-p-10">

                <div class="title-big p-title">
                    SITO IN<br>
                    COSTRUZIONE
                </div>
                <div class="text mt-2">
                    <?=$PAGE->domain?>
                </div>

                <?php if ($SOCIETY->prettyAddress != '--') : ?>
                <div class="text-small mt-6">
                    DOVE SIAMO?
                </div>
                <div class="w-100 mt-2">
                    <?=$SOCIETY->prettyAddress?>
                </div>
                <?php endif; ?>

                <?php if (!empty($SOCIETY->email) || !empty($SOCIETY->tel) || !empty($SOCIETY->cel)) : ?>
                <div class="text-small mt-6">
                    CONTATTI
                </div>
                <div class="w-100 mt-2 d-grid col-1 gap-2">
                    <a href="tel:+"></a>
                    <?=empty($SOCIETY->email) ? "" : "<span><i class=\"bi bi-envelope tx-primary\"></i> <a href=\"mailto:$SOCIETY->email\">$SOCIETY->email</a></span>"?>
                    <?=empty($SOCIETY->tel) ? "" : "<span><i class=\"bi bi-telephone tx-primary\"></i> <a href=\"tel:$SOCIETY->tel\">".prettyPhone($SOCIETY->tel)."</a></span>"?>
                    <?=empty($SOCIETY->cel) ? "" : "<span><i class=\"bi bi-phone tx-primary\"></i> <a href=\"tel:$SOCIETY->cel\">".prettyPhone($SOCIETY->cel)."</a></span>"?>
                </div>
                <?php endif; ?>

                <?php if (!empty($SOCIETY->social)) : ?>
                <div class="text-small mt-6">
                    SEGUICI SU
                </div>
                <div class="w-100 mt-2 d-flex gap-2">
                    <?php

                        foreach ($SOCIETY->social as $social => $url) {

                            echo "<a href=\"$url\" class=\"btn btn-icon btn-primary\"> <i class=\"bi bi-$social\"></i> </a>";

                        }

                    ?>
                </div>
                <?php endif; ?>

            </div>

            <div class="p-a w-100 bottom p-p-r mt-p-10">
                <div class="text a-c">
                    <?=$SOCIETY->prettyLegal?> <br>
                    Credit by <a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer">Wonder Image</a>
                </div>
            </div>
        </div>
    </section>
    
    <?php include $ROOT_APP.'/utility/frontend/body-end.php' ?>
    
</body>
</html>