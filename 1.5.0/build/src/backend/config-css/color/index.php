<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    require_once "set-up.php";

    $ALERT = '';

    if (!isset($NAME->database) || $NAME->database == '') { 
        $NAME->database = 'main';
    } else {
        $mysqli = $MYSQLI_CONNECTION[$NAME->database];
    }

    if (!isset($PAGE_TABLE)) {
        $table = strtoupper($NAME->table);
        $PAGE_TABLE = $TABLE->$table;
    }
    
    if (!empty($_GET['modify'])) {
        
        $TITLE = "Modifica $TEXT->titleS";

        $SQL = sqlSelect($NAME->table, ['id' => $_GET['modify']], 1);
        $VALUES = $SQL->row;

    } else {

        $TITLE = "Aggiungi $TEXT->titleS";
        $VALUES = [];

    }

    $REDIRECT = !empty($PAGE->redirect) ? $PAGE->redirect : "list.php";

    if (isset($_POST['upload']) || isset($_POST['upload-add'])) {

        $POST = array_merge($_POST, $_FILES);
        $VALUES = formToArray($NAME->table, $POST, $PAGE_TABLE, isset($VALUES) ? $VALUES : null);
        
        if (empty($ALERT)) {
            if (!empty($_GET['modify']) || !empty($_POST['modify']) ) {

                $MODIFY_ID = empty($_GET['modify']) ? $_POST['modify'] : $_GET['modify'];
                sqlModify($NAME->table, $VALUES, 'id', $MODIFY_ID);

            } else {
        
                sqlInsert($NAME->table, $VALUES);
        
            }
        }

        if (empty($ALERT)) { cssRoot(); cssColor(); }

        if (empty($ALERT)) {

            $LOCATION = isset($_POST['upload-add']) ? "index.php?redirect=$PAGE->redirectBase64" : $REDIRECT;
            
            header("Location: $LOCATION");
            exit;

        }

    }

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
                    <?=text('Var', 'var', 'required'); ?>
                </div>
                <div class="col-8">
                    <?=text('Nome', 'name', 'required'); ?>
                </div>
            </wi-card>

            <wi-card class="col-3">
                <div class="col-12">
                    <?=color('Colore', 'color', 'required'); ?>
                </div>
                <div class="col-12">
                    <?=color('Contrasto', 'contrast', 'required'); ?>
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