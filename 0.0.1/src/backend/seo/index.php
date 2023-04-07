<?php

    $BACKEND = true;
    $PRIVATE = true;
    $PERMIT = ['admin'];

    $ROOT = $_SERVER['DOCUMENT_ROOT'];
    require_once $ROOT."/vendor/wonder-image/app/wonder-image.php";

    $INFO_PAGE = (object) array();
    $INFO_PAGE->title = "Seo";
    $INFO_PAGE->table = "seo";

    $SQL = sqlSelect($INFO_PAGE->table, ['id' => 1], 1);
    $VALUES = $SQL->row;

    if (isset($_POST['modify'])) {
        
        $VALUES = formToArray($INFO_PAGE->table, $_POST, $TABLE->SEO);
        
        if (empty($ALERT)) { sqlModify($INFO_PAGE->table, $VALUES, 'id', 1); }
        if (empty($ALERT)) { header("Location: ?alert=652"); }

    }

?>
<!DOCTYPE html>
<html lang="it">
<head>

    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?=$INFO_PAGE->title?></title>

    <?php include $ROOT_APP."/utility/backend/head.php"; ?>

</head>
<body>
    
    <?php include $ROOT_APP."/utility/backend/body-start.php"; ?>
    <?php include $ROOT_APP."/utility/backend/header.php"; ?>

    <form action="" method="post" enctype="multipart/form-data" onsubmit="loadingSpinner()">
        <div class="row g-3">

            <wi-card class="col-12">
                <h3>Impostazioni SEO</h3>
            </wi-card>

            <wi-card class="col-9">
                <div class="col-12">
                    <h6>Default SEO</h6>
                </div>
                <div class="col-8">
                    <?=text('Titolo', 'title', 'required'); ?>
                </div>
                <div class="col-12">
                    <?=textarea('Descrizione', 'description', 'required'); ?>
                </div>
                <div class="col-6">
                    <?=text('Autore', 'author', 'required'); ?>
                </div>
                <div class="col-6">
                    <?=text('Copyright', 'copyright', 'required'); ?>
                </div>
            </wi-card>

            <wi-card class="col-3">
                <div class="col-12">
                    <h6>Creatore</h6>
                </div>
                <div class="col-12">
                    <?=text('Nome', 'creator', 'required'); ?>
                </div>
                <div class="col-12">
                    <?=email('Email', 'reply', 'required'); ?>
                </div>
                <div class="col-12">
                    <?=submit('Modifica seo', 'modify'); ?>
                </div>
            </wi-card>

        </div>
    </form>

    <?php include $ROOT_APP."/utility/backend/footer.php"; ?>
    <?php include $ROOT_APP."/utility/backend/body-end.php"; ?>

</body>
</html>