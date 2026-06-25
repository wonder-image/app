<!DOCTYPE html>
<html lang="it">
<head>

    <?= \Wonder\View\View::component('frontend.layout.head') ?>

    <link href="https://fonts.googleapis.com/css2?family=Montserrat:ital,wght@0,100..900;1,100..900&display=swap" rel="stylesheet">

    <style>
        :root {

            --spacer: 4px;
            --header-height: 80px;

            --bg-color: #ffffff;
            --tx-color: #000000;

            --font-family: "Montserrat", sans-serif;
            --font-size: 16px;
            --line-height: 16px;
            --font-weight: 400;

            --title-big-font-size: 65px;
            --title-big-line-height: 65px;
            --title-big-font-family: var(--font-family);
            --title-big-font-weight: 700;

            --title-font-size: 32px;
            --title-line-height: 32px;
            --title-font-family: var(--font-family);
            --title-font-weight: 500;

            --subtitle-font-size: 24px;
            --subtitle-line-height: 24px;
            --subtitle-font-family: var(--font-family);
            --subtitle-font-weight: 400;

            --text-font-size: 16px;
            --text-line-height: 20px;
            --text-font-family: var(--font-family);
            --text-font-weight: 300;

            --text-small-font-size: 12px;
            --text-small-line-height: 12px;
            --text-small-font-family: var(--font-family);
            --text-small-font-weight: 300;

            --button-font-weight: 400;
            --button-font-size: 14px;
            --button-line-height: 16px;
            --button-border-radius: 5px;
            --button-border-width: 2px;

            --badge-font-weight: 400;
            --badge-font-size: 12px;
            --badge-line-height: 12px;
            --badge-border-radius: 5px;
            --badge-border-width: 1px;

            --primary-color: #1e90ff;
            --primary-o-color: #ffffff;
            --primary-color-rgb: 30, 144, 255;
            --primary-o-color-rgb: 255, 255, 255;
        }
    </style>

</head>
<body>

    <?= \Wonder\View\View::component('frontend.layout.body-start') ?>

    <section class="full-page h-p-auto">
        <div class="content">

            <div class="p-a w-50 end c-h p-p-r w-p-100 p-c-h-off">
                <img src="<?=e(($PATH->appAssets ?? '').'/images/under-construction.png')?>" alt="Sito in costruzione" class="w-100">
            </div>

            <div class="p-a w-50 c-h p-p-r w-p-100 p-c-h-off mt-p-10">

                <div class="title-big t-title">
                    SITO IN<br>
                    MANUTENZIONE
                </div>
                <div class="text mt-2">
                    <?=e($PAGE->domain ?? '')?>
                </div>

                <?php if (($SOCIETY->prettyAddress ?? '--') !== '--') : ?>
                <div class="text-small mt-6">
                    DOVE SIAMO?
                </div>
                <div class="w-100 mt-2">
                    <i class="bi bi-geo-alt tx-primary"></i>
                    <span>
                        <?php if (empty($SOCIETY->gmaps)) { ?>
                            <?=e($SOCIETY->prettyAddress ?? '')?>
                        <?php } else { ?>
                            <a href="<?=e($SOCIETY->gmaps)?>" target="_blank" rel="noopener noreferrer"><?=e($SOCIETY->prettyAddress ?? '')?></a>
                        <?php } ?>
                    </span>
                </div>
                <?php endif; ?>

                <?php if (!empty($SOCIETY->email) || !empty($SOCIETY->tel) || !empty($SOCIETY->cel)) : ?>
                <div class="text-small mt-6">
                    CONTATTI
                </div>
                <div class="w-100 mt-2 d-grid col-1 gap-2">
                    <?php if (!empty($SOCIETY->email)) { ?>
                    <span class="w-100 d-flex gap-2"><i class="bi bi-envelope tx-primary"></i> <a href="mailto:<?=e($SOCIETY->email)?>"><?=e($SOCIETY->email)?></a></span>
                    <?php } ?>
                    <?php if (!empty($SOCIETY->tel)) { ?>
                    <span class="w-100 d-flex gap-2"><i class="bi bi-telephone tx-primary"></i> <a href="tel:<?=e($SOCIETY->tel)?>"><?=e(prettyPhone($SOCIETY->tel))?></a></span>
                    <?php } ?>
                    <?php if (!empty($SOCIETY->cel)) { ?>
                    <span class="w-100 d-flex gap-2"><i class="bi bi-phone tx-primary"></i> <a href="tel:<?=e($SOCIETY->cel)?>"><?=e(prettyPhone($SOCIETY->cel))?></a></span>
                    <?php } ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($SOCIETY->social) && is_iterable($SOCIETY->social)) : ?>
                <div class="text-small mt-6">
                    SEGUICI SU
                </div>
                <div class="w-100 mt-2 d-flex gap-2">
                    <?php foreach ($SOCIETY->social as $social => $url) { ?>
                    <a href="<?=e($url)?>" class="btn btn-icon btn-primary"><i class="bi bi-<?=e($social)?>"></i></a>
                    <?php } ?>
                </div>
                <?php endif; ?>

            </div>

            <div class="p-a w-100 bottom p-p-r mt-p-10">
                <div class="text a-c">
                    <?=e($SOCIETY->prettyLegal ?? '')?> <br>
                    Credit by <a href="https://www.wonderimage.it" target="_blank" rel="noopener noreferrer">Wonder Image</a>
                </div>
            </div>
        </div>
    </section>

    <?= \Wonder\View\View::component('frontend.layout.body-end') ?>

</body>
</html>
