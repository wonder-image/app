<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    $REDIRECT = !empty($PAGE->redirect) ? $PAGE->redirect : "$PATH->backend/$NAME->folder/list.php";

    $EVENT = info($NAME->table, 'id', $_GET['id']);

    $U = infoUser($EVENT->user_id);
    
    if ($U->exists) {
        $userName = $U->fullName;
    } else {
        $userName = "Utente non definito";
    }
    
    $DOCUMENT = infoLegalDocument($EVENT->legal_document_id);
    $documentName = trim((string) ($DOCUMENT->name ?? ''));
    if ($documentName === '') {
        $documentName = trim((string) ($DOCUMENT->doc_type ?? 'Documento legale'));
    }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$EVENT->consent_type?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-12">
            <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$documentName?> </h3>
            <h6><?= $userName ?></h6>
        </wi-card>

        <div class="col-9">
            <div class="row g-3">

                <wi-card class="col-6">
                    <h6 class="col-12 mb-2"> Documento </h6>
                    <div class="col-12">
                        Nome: <b><?=$documentName?></b> <br>
                        Tipologia: <b><?=$DOCUMENT->doc_type?></b> <br>
                        Versione: <b><?=$DOCUMENT->version?></b> <br>
                        Lingua: <b><?=$DOCUMENT->language_code?></b>
                    </div>
                </wi-card>

                <wi-card class="col-6">
                    <h6 class="col-12 mb-2"> Risposta utente </h6>
                    <div class="col-12">
                        Fonte: <b><?=consentEventSource($DOCUMENT->source)->text?></b> <br>
                        Azione: <?=consentEventAction($DOCUMENT->action)->badge?>
                    </div>

                </wi-card>

                <wi-card class="col-12">
                    <h6 class="col-12"> Prova </h6>
                    <div class="col-12">
                        <?= wiCard($EVENT->evidence_json) ?>
                    </div>
                </wi-card>

            </div>
        </div>

        <div class="col-3">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12 mb-2"> Altro </h6>
                    <div class="col-6">
                        Stato: <b><?=$EVENT->locale?></b> <br>
                        IP: <b><?=$EVENT->ip_address?></b> <br>
                        Browser: <b><?=$EVENT->user_agent?></b> <br>
                        Creazione: <b><?=$EVENT->creation?></b>
                    </div>
                </wi-card>

            </div>
        </div>
    
    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>
