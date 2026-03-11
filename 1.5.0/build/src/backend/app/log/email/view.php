<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    $REDIRECT = !empty($PAGE->redirect) ? $PAGE->redirect : "$PATH->backend/$NAME->folder/list.php";

    $MAIL = info($NAME->table, 'id', $_GET['id']);

    $U = infoUser($MAIL->user_id);
    
    if ($U->exists) {
        $userName = $U->fullName;
    } else {
        $userName = "Utente non definito";
    }
    
?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$MAIL->subject?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-12">
            <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$MAIL->subject?> </h3>
            <h6><?= $userName ?></h6>
        </wi-card>

        <div class="col-9">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12 mb-2"> Dettagli </h6>
                    <div class="col-12">
                        Mittente: <b><?=$MAIL->from_email?></b> <br>
                        Risposta: <b><?=$MAIL->reply_to_email?></b> <br>
                        Destinatario: <b><?=$MAIL->to_email?></b> <br>
                        Template: <b><?=$MAIL->template?></b>
                    </div>
                </wi-card>

                <wi-card class="col-6">
                    <h6 class="col-12"> Messaggio </h6>
                    <div class="col-12">
                        <?= wiCard($MAIL->body_text) ?>
                    </div>
                    <h6 class="col-12"> Allegati </h6>
                    <div class="col-12">
                        <?= wiCard($MAIL->attachments) ?>
                    </div>
                </wi-card>

                <wi-card class="col-6">
                    <h6 class="col-12"> Errori </h6>
                    <div class="col-12">
                        <?= wiCard($MAIL->error_message) ?>
                    </div>
                </wi-card>

            </div>
        </div>

        <div class="col-3">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12 mb-2"> Altro </h6>
                    <div class="col-12">
                        Servizio: <b><?=mailService($MAIL->service)->text?></b> <br>
                        IP: <b><?=$MAIL->ip?></b> <br>
                        Browser: <b><?=$MAIL->user_agent?></b> <br>
                        Stato: <b><?=mailLogStatus($MAIL->status)->badge?></b>
                    </div>
                </wi-card>

            </div>
        </div>
    
    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>