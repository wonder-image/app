<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = [];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    $REDIRECT = !empty($PAGE->redirect) ? $PAGE->redirect : "$PATH->backend/$NAME->folder/list.php";

    $LOG = info($NAME->table, 'id', $_GET['id']);

    $U = infoUser($LOG->user_id);
    
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
    <title><?=$LOG->event?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>

    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <div class="row g-3">

        <wi-card class="col-12">
            <h3><a href="<?=$REDIRECT?>" type="button" class="text-dark"><i class="bi bi-arrow-left-short"></i></a> <?=$LOG->event?> </h3>
            <h6><?= $userName ?></h6>
        </wi-card>

        <div class="col-9">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12"> Dettagli </h6>
                    <div class="col-6 mt-2">
                        Evento: <b><?=$LOG->event?></b> <br>
                        Area: <b><?=$LOG->area?></b> <br>
                        IP: <b><?=$LOG->ip?></b> <br>
                        User Agent: <b><?=$LOG->user_agent?></b>
                    </div>
                </wi-card>

            </div>
        </div>

        <div class="col-3">
            <div class="row g-3">

                <wi-card class="col-12">
                    <h6 class="col-12"> Metadati </h6>
                    <div class="col-12">
                        <?= wiCard($LOG->meta) ?>
                    </div>
                </wi-card>

            </div>
        </div>
    
    </div>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>