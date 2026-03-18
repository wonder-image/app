<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    $REDIRECT = !empty($PAGE->redirect) ? $PAGE->redirect : "$PATH->backend/$NAME->folder/list.php";

    $DOCUMENT = infoLegalDocument($_GET['id'] ?? 0);

    if (!$DOCUMENT->exists) {
        header("Location: $REDIRECT");
        exit;
    }

    $documentTitle = trim((string) ($DOCUMENT->renderName ?? $DOCUMENT->name ?? 'Documento legale'));
    if ($documentTitle === '') {
        $documentTitle = trim((string) ($DOCUMENT->doc_type ?? 'Documento legale'));
    }

    $downloadUrl = "$PATH->backend/$NAME->folder/download.php?id=".$DOCUMENT->id;

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$documentTitle?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-12">
            <div class="row g-2 align-items-center">
                <div class="col">
                    <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$documentTitle?></h3>
                </div>
                <div class="col-auto">
                    <a href="<?=$downloadUrl?>" target="_blank" rel="noopener noreferrer" class="btn btn-dark btn-sm">
                        <i class="bi bi-download"></i> Scarica PDF
                    </a>
                </div>
            </div>
        </wi-card>

        <div class="col-4">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12 mb-2"> Documento </h6>
                    <div class="col-12">
                        Nome: <b><?=$documentTitle?></b> <br>
                        Tipologia: <b><?=$DOCUMENT->doc_type?></b> <br>
                        Versione: <b><?=$DOCUMENT->version?></b> <br>
                        Lingua: <b><?=$DOCUMENT->language_code?></b> <br>
                        Pubblicato: <b><?=$DOCUMENT->published_at?></b> <br>
                        Stato: <b><?=active($DOCUMENT->active, $DOCUMENT->id)->badge ?? $DOCUMENT->active?></b>
                    </div>
                </wi-card>

                <wi-card class="col-12">
                    <h6 class="col-12 mb-2"> Checkbox </h6>
                    <div class="col-12">
                        <?= wiCard($DOCUMENT->renderLabel) ?>
                    </div>
                </wi-card>

            </div>
        </div>

        <div class="col-8">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12 mb-2"> Testo documento </h6>
                    <div class="col-12">
                        <?= wiCard($DOCUMENT->renderContent) ?>
                    </div>
                </wi-card>

            </div>
        </div>

    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>
