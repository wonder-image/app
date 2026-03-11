<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    if (isset($_POST['content_snapshot'])) {
        $_POST['content_hash'] = hash('sha256', $_POST['content_snapshot']);
    }
    
    require_once $ROOT_APP."/html/backend/index.php";

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$TITLE?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <div class="row g-3">

            <wi-card class="col-12">
                <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$TITLE?></h3>
            </wi-card>

            <wi-card class="col-9">
                <div class="col-4">
                    <?=select('Tipologia documento', 'doc_type', legalDocumentTypes(), 'old', 'required'); ?>
                </div>
                <div class="col-4">
                    <?=select('Lingua', 'language_code', array_map(fn($lang) => $lang['name'], __ls()), 'old', 'required'); ?>
                </div>
                <div class="col-4">
                    <?=text('Versione', 'version', 'required'); ?>
                </div>
                <div class="col-4">
                    <?php

                        $publishedAtDefault = isset($VALUES['published_at']) && strtotime((string) $VALUES['published_at']) !== false
                            ? date('Y-m-d\TH:i', strtotime((string) $VALUES['published_at']))
                            : date('Y-m-d\TH:i');

                        echo textDatetime('Pubblicato il', 'published_at', 'required', $publishedAtDefault);

                    ?>
                </div>
                <div class="col-12">
                    <?=text('Testo checkbox', 'checkbox_label', 'required'); ?>
                </div>
                <div class="col-12">
                    <?=textarea('Testo', 'content_snapshot', 'required', 'blog'); ?>
                </div>
            </wi-card>

            <wi-card class="col-3">
                <div class="col-12">
                    <?=select('Stato', 'is_active', [ '1' => 'Attivo', '0' => 'Non attivo' ], 'old', 'required'); ?>
                </div>
                <div class="col-12">
                    <?=submitAdd()?>
                </div>
            </wi-card>

        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>
